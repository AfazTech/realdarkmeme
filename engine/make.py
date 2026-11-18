#!/usr/bin/env python3
"""Create standardized 1080x1920 Instagram Reels with blur and a mandatory cover.

Original assets are never modified; the MP4 is generated atomically.
"""

import argparse
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import threading
import time
import unicodedata
from functools import lru_cache

from PIL import Image, ImageDraw, ImageFont, ImageColor, features
from settings import RenderOptions, load_settings

HOME = Path(__file__).resolve().parent


def watermark_filter(options: RenderOptions, watermark_file):
    """Animated watermark. Keep user-entered text in a file to avoid filter injection."""
    # Temp directory is created by tempfile (alphanumeric basename).
    text_file = str(watermark_file).replace('\\', '\\\\').replace(':', '\\:').replace("'", "\\'")
    font_arg = ''
    if options.watermark_font:
        f = str(Path(options.watermark_font).expanduser().resolve())
        if not Path(f).is_file():
            raise ValueError(f'Watermark font not found: {f}')
        font_arg = "fontfile='" + f.replace('\\', '\\\\').replace(':', '\\:').replace("'", "\\'") + "':"
    opacity = options.watermark_opacity / 100
    color = options.watermark_color.lstrip('#')
    outline = options.watermark_outline.lstrip('#')
    # Escape commas inside FFmpeg expressions, not user text.
    return (
        f"drawtext=textfile='{text_file}':{font_arg}fontsize={options.watermark_size}:"
        f"fontcolor=0x{color}@{opacity:.2f}:borderw={options.watermark_border}:"
        f"bordercolor=0x{outline}:"
        f"x='(w-text_w)-abs(mod(t*{options.watermark_speed_x}\\,2*max(w-text_w\\,1))-(w-text_w))':"
        f"y='(h-text_h)-abs(mod(t*{options.watermark_speed_y}\\,2*max(h-text_h\\,1))-(h-text_h))'"
    )


def command(args, capture=False):
    result = subprocess.run(args, stdout=subprocess.PIPE if capture else subprocess.DEVNULL,
                            stderr=subprocess.PIPE)
    if result.returncode:
        raise RuntimeError(result.stderr.decode('utf-8', errors='replace').strip())
    return result.stdout if capture else None


def font_path(custom):
    if custom:
        font = Path(custom).expanduser().resolve()
        if not font.is_file():
            raise ValueError(f'Font file not found: {font}')
        return str(font)

    # Check the actual font file/style rather than fc-match's often incorrect fallback.
    preferred = (
        ('Vazirmatn', ('Black', 'ExtraBold', 'Bold')),
        ('Noto Sans Arabic', ('Black', 'ExtraBold', 'Bold')),
        ('Noto Kufi Arabic', ('Black', 'ExtraBold', 'Bold')),
        ('DejaVu Sans', ('Bold',)),
    )
    if shutil.which('fc-list'):
        raw = command(['fc-list', '-f', '%{family}|%{style}|%{file}\n'], capture=True)
        entries = [line.split('|', 2) for line in raw.decode('utf-8', errors='replace').splitlines()]
        for family, desired_styles in preferred:
            for style in desired_styles:
                for entry in entries:
                    if (len(entry) == 3 and family.lower() in entry[0].lower()
                            and style.lower() in entry[1].lower() and Path(entry[2]).is_file()):
                        return entry[2]
    raise RuntimeError('No Persian font found. Install Vazirmatn/Noto Kufi Arabic or use --font FONT.ttf')


def shaped(text):
    if features.check('raqm'):
        return text, {'direction': 'rtl', 'language': 'fa'}
    try:
        import arabic_reshaper
        from bidi.algorithm import get_display
    except ImportError as exc:
        raise RuntimeError('Install Pillow with libraqm or: pip install arabic-reshaper python-bidi') from exc
    return get_display(arabic_reshaper.reshape(text)), {}


def _is_emoji_base(char):
    cp = ord(char)
    return (0x1F000 <= cp <= 0x1FAFF or 0x2600 <= cp <= 0x27BF
            or cp in (0x00A9, 0x00AE, 0x203C, 0x2049, 0x2122, 0x2139,
                      0x2194, 0x2195, 0x21A9, 0x21AA, 0x231A, 0x231B,
                      0x2328, 0x23CF, 0x24C2, 0x25AA, 0x25AB, 0x25B6,
                      0x25C0, 0x25FB, 0x25FC, 0x25FD, 0x25FE, 0x2934, 0x2935,
                      0x3030, 0x303D, 0x3297, 0x3299))


def _graphemes(text):
    """Keep emoji sequences (flags, skin tones, ZWJ families, keycaps) together.

    `regex` is optional; common emoji sequences also work without it.
    """
    try:
        import regex
        yield from regex.findall(r'\X', text)
        return
    except ImportError:
        pass

    i = 0
    while i < len(text):
        c = text[i]
        j = i + 1
        if (c in '0123456789#*' and j < len(text)
                and text[j] in ('\ufe0f', '\u20e3')):
            if text[j] == '\ufe0f':
                j += 1
            if j < len(text) and text[j] == '\u20e3':
                yield text[i:j + 1]
                i = j + 1
                continue
        if 0x1F1E6 <= ord(c) <= 0x1F1FF and j < len(text) and 0x1F1E6 <= ord(text[j]) <= 0x1F1FF:
            yield text[i:j + 1]
            i = j + 1
            continue
        if _is_emoji_base(c):
            while j < len(text) and (text[j] in ('\ufe0f', '\ufe0e', '\u20e3')
                                       or 0x1F3FB <= ord(text[j]) <= 0x1F3FF):
                j += 1
            while j + 1 < len(text) and text[j] == '\u200d' and _is_emoji_base(text[j + 1]):
                j += 2
                while j < len(text) and (text[j] in ('\ufe0f', '\ufe0e')
                                           or 0x1F3FB <= ord(text[j]) <= 0x1F3FF):
                    j += 1
            yield text[i:j]
            i = j
            continue
        yield c
        i += 1


def _is_emoji(cluster):
    if '\ufe0e' in cluster and '\ufe0f' not in cluster:
        return False  # explicit text presentation
    return (any(_is_emoji_base(c) for c in cluster)
            or '\u20e3' in cluster or '\ufe0f' in cluster)


@lru_cache(maxsize=512)
def split_runs(text):
    """Split into normal-text runs and complete emoji clusters, in logical order."""
    result = []
    ordinary = []
    for cluster in _graphemes(text):
        if _is_emoji(cluster):
            if ordinary:
                result.append(('text', ''.join(ordinary)))
                ordinary.clear()
            result.append(('emoji', cluster))
        else:
            ordinary.append(cluster)
    if ordinary:
        result.append(('text', ''.join(ordinary)))
    return tuple(result)


def emoji_font_path(custom):
    if custom:
        p = Path(custom).expanduser().resolve()
        if not p.is_file():
            raise ValueError(f'Emoji font file not found: {p}')
        return str(p)

    if shutil.which('fc-list'):
        raw = command(['fc-list', '-f', '%{family}|%{file}\n'], capture=True)
        candidates = []
        for line in raw.decode('utf-8', errors='replace').splitlines():
            if '|' not in line:
                continue
            family, file = line.split('|', 1)
            if 'emoji' in family.lower() and Path(file).is_file():
                candidates.append((0 if 'color' in family.lower() else 1, file))
        if candidates:
            return sorted(candidates)[0][1]

    for path in ('/usr/share/fonts/truetype/noto/NotoColorEmoji.ttf',
                 '/usr/share/fonts/noto/NotoColorEmoji.ttf',
                 '/usr/share/fonts/truetype/emoji/NotoColorEmoji.ttf'):
        if Path(path).is_file():
            return path
    raise RuntimeError('Emoji font not found. On Arch Linux: sudo pacman -S noto-fonts-emoji '
                       '(or specify --emoji-font /path/to/NotoColorEmoji.ttf)')


@lru_cache(maxsize=8)
def _load_emoji_font(path):
    # Noto Color Emoji is a bitmap font and only loads at its native strike.
    for size in (109, 128, 136, 160, 72, 64, 32, 16, 256):
        try:
            return ImageFont.truetype(path, size)
        except OSError:
            continue
    raise RuntimeError(f'Cannot load emoji font: {path}')


@lru_cache(maxsize=512)
def _emoji_source(emoji, path):
    font = _load_emoji_font(path)
    bbox = font.getbbox(emoji, direction='ltr')
    w, h = bbox[2] - bbox[0], bbox[3] - bbox[1]
    if w <= 0 or h <= 0:
        raise RuntimeError(f'Emoji font cannot render: {emoji}')
    img = Image.new('RGBA', (w + 12, h + 12), (0, 0, 0, 0))
    ImageDraw.Draw(img).text((6 - bbox[0], 6 - bbox[1]), emoji,
                             font=font, embedded_color=True, direction='ltr')
    nonempty = img.getbbox()
    if not nonempty:
        raise RuntimeError(f'Emoji font cannot render: {emoji}')
    return img.crop(nonempty)


@lru_cache(maxsize=1024)
def _emoji_image(emoji, font_size, path):
    src = _emoji_source(emoji, path)
    target_h = max(1, round(font_size * 0.84))
    target_w = max(1, round(src.width * target_h / src.height))
    return src.resize((target_w, target_h), Image.Resampling.LANCZOS)


def _base_direction(text):
    for char in text:
        bidi = unicodedata.bidirectional(char)
        if bidi in ('R', 'AL'):
            return 'rtl'
        if bidi == 'L':
            return 'ltr'
    return 'rtl'


def _text_length(draw, text, font):
    display, opts = shaped(text)
    return draw.textlength(display, font=font, **opts)


def title_width(draw, text, font, emoji_path):
    return sum((_text_length(draw, run, font) if kind == 'text'
                else _emoji_image(run, font.size, emoji_path).width)
               for kind, run in split_runs(text))


def wrap(text, draw, font, emoji_path, max_width):
    lines = []
    for block in text.split('\n'):
        words = block.split()
        if not words:
            lines.append('')
            continue
        line = words[0]
        for word in words[1:]:
            candidate = line + ' ' + word
            if title_width(draw, candidate, font, emoji_path) <= max_width:
                line = candidate
            else:
                lines.append(line)
                line = word
        lines.append(line)
    return lines


def draw_mixed_title(image, line, font, emoji_path, cx, cy, color=(10, 10, 10, 255)):
    draw = ImageDraw.Draw(image)
    direction = _base_direction(line)
    width = title_width(draw, line, font, emoji_path)
    cursor = cx + (width / 2 if direction == 'rtl' else -width / 2)

    for kind, run in split_runs(line):
        if kind == 'text':
            display, opts = shaped(run)
            length = draw.textlength(display, font=font, **opts)
            draw.text((round(cursor), round(cy)), display,
                      anchor='rm' if direction == 'rtl' else 'lm',
                      fill=color, font=font, **opts)
        else:
            icon = _emoji_image(run, font.size, emoji_path)
            length = icon.width
            x = round(cursor - length) if direction == 'rtl' else round(cursor)
            image.alpha_composite(icon, (x, round(cy - icon.height / 2)))
        cursor += -length if direction == 'rtl' else length


def write_title(cover_file, title, font_file, emoji_font=None, font_size=None,
                panel_width=93, title_x=50.0, title_y=64.9,
                title_width_pct=73, title_max_lines=3, title_color='#0A0A0A'):
    image = Image.open(cover_file).convert('RGBA')
    # Lower brush is scaled horizontally while the logo above stays unchanged.
    top, bottom = round(645 * image.height / 1448), round(1310 * image.height / 1448)
    panel = image.crop((0, top, image.width, bottom))
    narrow = panel.resize((max(1, round(panel.width * panel_width / 100)), panel.height), Image.Resampling.LANCZOS)
    image.paste((0, 0, 0, 0), (0, top, image.width, bottom))
    image.alpha_composite(narrow, ((image.width - narrow.width) // 2, top))
    if not title.strip():
        return image
    draw = ImageDraw.Draw(image)
    sx, sy = image.width / 1086, image.height / 1448
    scale = min(sx, sy)
    cx, cy = round(image.width * title_x / 100), round(image.height * title_y / 100)
    max_w = round(image.width * title_width_pct / 100 * panel_width / 93)
    max_h = round(410 * sy)
    needs_emoji = any(kind == 'emoji' for kind, _ in split_runs(title))
    emoji_path = emoji_font_path(emoji_font) if needs_emoji else None
    color = (*ImageColor.getrgb(title_color), 255)

    def layout(size):
        font = ImageFont.truetype(font_file, size)
        lines = wrap(title, draw, font, emoji_path, max_w)
        line_height = round(size * 1.18)
        if (len(lines) > title_max_lines or len(lines) * line_height > max_h
                or any(title_width(draw, line, font, emoji_path) > max_w for line in lines)):
            return None
        return font, lines, line_height

    if font_size is None:
        default_size = max(12, round(90 * scale))
        selected = next((candidate for size in range(default_size, 11, -1)
                         if (candidate := layout(size)) is not None), None)
        if selected is None:
            raise ValueError('Title does not fit in the cover. Try shorter text or a wider panel.')
    else:
        requested = max(1, round(font_size * scale))
        selected = layout(requested)
        if selected is None:
            raise ValueError(f'Font size {font_size} is too large for the title panel. '
                             'Decrease the font size or increase the text width.')
    font, lines, line_height = selected
    top = cy - len(lines) * line_height / 2
    for n, line in enumerate(lines):
        draw_mixed_title(image, line, font, emoji_path, cx,
                         round(top + (n + 0.5) * line_height), color)
    return image


REELS_W = 1080
REELS_H = 1920


def normalize_graph(source, label, blur_strength, prefix, source_size):
    """Return filter graph segments for uncropped 9:16 video over blurred fill.

    Labels are unique per invocation; the exact same filter is used to prepare
    both the preview/attached thumbnail and the main rendered frames.
    """
    src_w, src_h = source_size
    # Already 9:16: the background is completely hidden. Avoid blur and split,
    # which significantly lowers render speed on correctly sized source videos.
    if src_w * REELS_H == src_h * REELS_W:
        return [f'{source}scale={REELS_W}:{REELS_H}:flags=lanczos,'
                f'setsar=1,format=yuv420p[{label}]']

    bg, fg, blurred, foreground = (f'{prefix}_{name}' for name in ('bg', 'fg', 'blur', 'fgfit'))
    return [
        f'{source}split=2[{bg}][{fg}]',
        (f'[{bg}]scale={REELS_W}:{REELS_H}:force_original_aspect_ratio=increase:'
         f'flags=lanczos,crop={REELS_W}:{REELS_H},'
         f'gblur=sigma={blur_strength},setsar=1[{blurred}]'),
        (f'[{fg}]scale={REELS_W}:{REELS_H}:force_original_aspect_ratio=decrease:'
         f'flags=lanczos,setsar=1[{foreground}]'),
        (f'[{blurred}][{foreground}]overlay=(W-w)/2:(H-h)/2:'
         f'shortest=1:format=auto,setsar=1,format=yuv420p[{label}]'),
    ]


def build_thumbnail(video, art, thumbnail, blur_strength, source_size, wm_filter=None):
    """Compose cover over precisely the normalized first frame, not raw input."""
    graph = normalize_graph('[0:v:0]', 'normalized', blur_strength, 'thumb', source_size)
    source = '[normalized]'
    if wm_filter:
        graph.append(f'{source}{wm_filter}[thumbmarked]')
        source = '[thumbmarked]'
    args = ['ffmpeg', '-hide_banner', '-loglevel', 'error', '-i', str(video),
            '-filter_complex', ';'.join(graph), '-map', source,
            '-frames:v', '1', '-f', 'image2pipe', '-vcodec', 'png', 'pipe:1']
    frame_bytes = command(args, capture=True)
    bg = Image.open(io.BytesIO(frame_bytes)).convert('RGBA')
    scale = min(REELS_W / art.width, REELS_H / art.height)
    fg = art.resize((round(art.width * scale), round(art.height * scale)), Image.Resampling.LANCZOS)
    bg.alpha_composite(fg, ((REELS_W - fg.width) // 2, (REELS_H - fg.height) // 2))
    bg.convert('RGB').save(thumbnail, 'PNG')


def probe_video(video):
    raw = command(['ffprobe', '-v', 'error', '-select_streams', 'v:0',
                   '-show_entries', 'stream=width,height,r_frame_rate',
                   '-show_entries', 'format=duration', '-of', 'json', str(video)], capture=True)
    info = json.loads(raw)
    stream = info['streams'][0]
    fraction = stream.get('r_frame_rate', '30/1').split('/')
    fps = float(fraction[0]) / max(1, float(fraction[1]))
    if not (1 <= fps <= 120):
        fps = 30
    return int(stream['width']), int(stream['height']), float(info['format']['duration']), fps


def _validate(options: RenderOptions):
    video = Path(options.input_video).expanduser().resolve()
    cover = Path(options.cover_image).expanduser().resolve()
    outro = Path(options.outro_image).expanduser().resolve()
    output = Path(options.output_video).expanduser().resolve()
    if not video.is_file():
        raise FileNotFoundError(f'Input video not found: {video}')
    if not cover.is_file():
        raise FileNotFoundError(f'Thumbnail/cover file not found: {cover}')
    if options.outro_enabled and not outro.is_file():
        raise FileNotFoundError(f'Outro image not found: {outro}')
    if output.suffix.lower() != '.mp4':
        raise ValueError('The output file must end with .mp4')
    if output in (video, cover, outro):
        raise ValueError('Output must not overwrite any input asset')
    if options.font_size < 12 or options.font_size > 300:
        raise ValueError('Font size must be between 12 and 300')
    if not (55 <= options.panel_width <= 100):
        raise ValueError('Panel width must be between 55% and 100%')
    if not (1 <= options.title_width <= 100 and 0 <= options.title_x <= 100 and 0 <= options.title_y <= 100):
        raise ValueError('Title position/width is out of range')
    if not (1 <= options.title_max_lines <= 8):
        raise ValueError('Title line limit must be 1–8')
    if options.watermark_enabled:
        if not options.watermark_text.strip():
            raise ValueError('Watermark text is empty. Disable watermark or add text.')
        if not (8 <= options.watermark_size <= 120 and 0 <= options.watermark_opacity <= 100):
            raise ValueError('Invalid watermark size or opacity')
        if not (0 <= options.watermark_border <= 12 and 0 <= options.watermark_speed_x <= 1000
                and 0 <= options.watermark_speed_y <= 1000):
            raise ValueError('Invalid watermark outline or speed')
    if not (8 <= options.background_blur <= 60):
        raise ValueError('Background blur must be between 8 and 60')
    if options.outro_enabled and not (0.1 <= options.outro_seconds <= 30):
        raise ValueError('Outro duration must be between 0.1 and 30 seconds')
    if options.outro_fit not in ('contain', 'cover', 'stretch'):
        raise ValueError('Outro image fit must be contain, cover, or stretch')
    if not (0 <= options.crf <= 51) or options.preset not in (
            'ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower'):
        raise ValueError('Invalid video quality or encoding preset')
    if options.output_fps not in (0, 24, 25, 30, 50, 60):
        raise ValueError('Output FPS must be source, 24, 25, 30, 50, or 60')
    if options.audio_mode not in ('copy', 'aac', 'mute'):
        raise ValueError('Audio mode must be copy, aac, or mute')
    for name in ('title_color', 'watermark_color', 'watermark_outline', 'outro_background'):
        color = getattr(options, name)
        if len(color) != 7 or not color.startswith('#') or any(c not in '0123456789abcdefABCDEF' for c in color[1:]):
            raise ValueError(f'Invalid color {name}: use #RRGGBB')
    for tool in ('ffmpeg', 'ffprobe'):
        if not shutil.which(tool):
            raise RuntimeError(f'{tool} not found; install FFmpeg')
    return video, cover, outro, output


def render(title=None, font_size=None, progress=None, cancel=None, output=None,
           *, options: RenderOptions | None = None):
    """Render a new MP4 with mandatory cover, optional watermark/outro/audio.

    CLI's original positional API remains available. The GUI passes RenderOptions.
    Never modifies input assets. Replaces output atomically only after success.
    """
    if options is None:
        options = load_settings()
        options.title = title or ''
        if font_size is not None:
            options.auto_font_size = False
            options.font_size = font_size
        if output is not None:
            options.output_video = str(output)
    video, cover, outro, target = _validate(options)
    target.parent.mkdir(parents=True, exist_ok=True)

    def emit(pct, message):
        if progress:
            progress(pct, message)

    def check_cancel():
        if cancel is not None and cancel.is_set():
            raise InterruptedError('Cancelled')

    emit(0, 'Preparing assets')
    source_w, source_h, duration, src_fps = probe_video(video)
    w, h = REELS_W, REELS_H
    fps = options.output_fps or src_fps
    frame_rate = f'{fps:.6f}'
    check_cancel()
    with tempfile.TemporaryDirectory(prefix='dark-memes-') as tmp:
        tmp = Path(tmp)
        thumb = tmp / 'first_frame.png'
        textfile = tmp / 'watermark.txt'
        wm = None
        if options.watermark_enabled:
            textfile.write_text(options.watermark_text, encoding='utf-8')
            wm = watermark_filter(options, textfile)

        emit(2, 'Preparing cover text')
        art = write_title(cover, options.title,
                          font_path(options.title_font) if options.title.strip() else '',
                          options.emoji_font or None,
                          None if options.auto_font_size else options.font_size,
                          options.panel_width, options.title_x, options.title_y,
                          options.title_width, options.title_max_lines,
                          options.title_color)
        check_cancel()
        build_thumbnail(video, art, thumb, options.background_blur,
                        (source_w, source_h), wm)
        emit(8, 'Thumbnail generated')
        check_cancel()

        # First normalize the video; then add watermark and mandatory cover frame.
        graph = normalize_graph('[0:v:0]', 'normalized', options.background_blur,
                                'main', (source_w, source_h))
        source = '[normalized]'
        if wm:
            graph.append(f'{source}{wm}[marked]')
            source = '[marked]'
        graph.append(f'{source}[1:v:0]overlay=enable=eq(n\\,0):eof_action=repeat,'
                     f'fps={frame_rate},setsar=1,format=yuv420p,settb=AVTB,setpts=PTS-STARTPTS[vmain]')
        inputs = ['-i', str(video), '-i', str(thumb)]
        if options.outro_enabled:
            fit = options.outro_fit
            if fit == 'contain':
                scaling = (f'scale={w}:{h}:force_original_aspect_ratio=decrease,'
                           f'pad={w}:{h}:(ow-iw)/2:(oh-ih)/2:color=0x{options.outro_background[1:]}')
            elif fit == 'cover':
                scaling = (f'scale={w}:{h}:force_original_aspect_ratio=increase,'
                           f'crop={w}:{h}')
            else:
                scaling = f'scale={w}:{h}'
            graph.append(f'[2:v:0]{scaling},fps={frame_rate},'
                         f'trim=duration={options.outro_seconds:.3f},setsar=1,format=yuv420p,'
                         'settb=AVTB,setpts=PTS-STARTPTS[vout]')
            graph.append('[vmain][vout]concat=n=2:v=1:a=0[v]')
            inputs.extend(['-loop', '1', '-framerate', frame_rate, '-i', str(outro)])
        else:
            graph.append('[vmain]null[v]')
        fd, stage = tempfile.mkstemp(prefix='.render-', suffix='.mp4', dir=target.parent)
        os.close(fd)
        args = ['ffmpeg', '-hide_banner', '-nostats', '-loglevel', 'error', '-y',
                *inputs, '-filter_complex', ';'.join(graph), '-map', '[v]']
        if options.audio_mode != 'mute':
            args += ['-map', '0:a?']
        args += ['-map', '1:v:0', '-map_metadata', '0', '-map_chapters', '0',
                 '-c:v:0', 'libx264', '-preset', options.preset, '-crf', str(options.crf),
                 '-pix_fmt:v:0', 'yuv420p']
        if options.audio_mode == 'copy':
            args += ['-c:a', 'copy']
        elif options.audio_mode == 'aac':
            args += ['-c:a', 'aac', '-b:a', f'{options.audio_bitrate}k']
        args += ['-c:v:1', 'png', '-disposition:v:1', 'attached_pic',
                 '-movflags', '+faststart', '-progress', 'pipe:1', str(stage)]
        process = None
        try:
            process = subprocess.Popen(args, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                       text=True, bufsize=1)
            errors = []
            def drain():
                errors.append(process.stderr.read())
            reader = threading.Thread(target=drain, daemon=True)
            reader.start()
            max_progress = 8
            for line in process.stdout:
                if cancel is not None and cancel.is_set():
                    process.terminate()
                    try:
                        process.wait(timeout=3)
                    except subprocess.TimeoutExpired:
                        process.kill()
                    raise InterruptedError('Cancelled')
                key, _, val = line.strip().partition('=')
                if key in ('out_time_us', 'out_time_ms'):
                    try:
                        seconds = int(val) / 1_000_000
                        pct = min(99, 8 + int(91 * seconds / (duration + (options.outro_seconds if options.outro_enabled else 0))))
                        max_progress = max(max_progress, pct)
                        emit(max_progress, 'Rendering video')
                    except ValueError:
                        pass
            code = process.wait()
            reader.join(timeout=2)
            if code:
                raise RuntimeError((errors[0] if errors else 'FFmpeg failed').strip())
            check_cancel()
            if not Path(stage).stat().st_size:
                raise RuntimeError('Empty MP4 produced')
            os.replace(stage, target)
        finally:
            if process is not None and process.poll() is None:
                process.kill()
                process.wait()
            if os.path.exists(stage):
                os.unlink(stage)
    emit(100, 'Complete')
    return target


def main():
    parser = argparse.ArgumentParser(description='Dark Memes Reels video maker')
    parser.add_argument('title', nargs='?', help='Title for the thumbnail')
    parser.add_argument('--font-size', type=int, help='Exact font size (default auto)')
    parser.add_argument('--gui', action='store_true', help='Launch desktop interface')
    args = parser.parse_args()
    if args.gui or args.title is None:
        from app import launch
        launch()
        return
    try:
        result = render(args.title, args.font_size,
                        lambda p, m: print(f'\r{p:3d}% {m}', end='', flush=True))
    except (ValueError, KeyError, RuntimeError, OSError, IndexError, InterruptedError) as exc:
        parser.exit(1, f'\nError: {exc}\n')
    print(f'\nDone: {result}')
    print('Original source files were not modified.')


if __name__ == '__main__':
    main()
