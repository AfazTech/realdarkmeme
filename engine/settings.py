"""Persistent project-local settings (only Python standard library required)."""
from __future__ import annotations

from dataclasses import asdict, dataclass, fields
import json
import os
from pathlib import Path
import tempfile

HOME = Path(__file__).resolve().parent
SETTINGS_FILE = HOME / 'settings.json'
LEGACY_SETTINGS_FILE = (Path(os.environ.get('XDG_CONFIG_HOME', str(Path.home() / '.config')))
                        .expanduser() / 'dark-memes-reels-maker' / 'settings.json')


@dataclass
class RenderOptions:
    input_video: str = str(HOME / 'main.mp4')
    cover_image: str = str(HOME / 'thumbnail.png')
    outro_image: str = str(HOME / 'outro.png')
    output_video: str = str(HOME / 'final.mp4')
    title: str = ''  # Transient: NEVER written to settings.json
    auto_font_size: bool = True
    font_size: int = 90
    title_font: str = ''
    emoji_font: str = ''
    title_color: str = '#0A0A0A'
    panel_width: int = 93
    title_x: float = 50.0
    title_y: float = 64.9
    title_width: int = 73
    title_max_lines: int = 3
    watermark_enabled: bool = True
    watermark_text: str = '@realdarkmeme'
    watermark_font: str = ''
    watermark_size: int = 30
    watermark_opacity: int = 70
    watermark_border: int = 2
    watermark_speed_x: int = 130
    watermark_speed_y: int = 75
    watermark_color: str = '#FFFFFF'
    watermark_outline: str = '#000000'
    background_blur: int = 25  # Blur strength for 1080x1920 letterboxing
    outro_enabled: bool = True
    outro_seconds: float = 1.0
    outro_fit: str = 'contain'
    outro_background: str = '#000000'
    crf: int = 18
    preset: str = 'fast'
    output_fps: int = 30  # Default: Instagram-friendly constant 30 FPS; 0: source
    audio_mode: str = 'aac'  # aac (Instagram-friendly), copy, mute
    audio_bitrate: int = 192


def from_mapping(data: dict) -> RenderOptions:
    """Ignore unexpected keys; ignore wrong-typed values rather than corrupt settings."""
    defaults = asdict(RenderOptions())
    if not isinstance(data, dict):
        raise ValueError('Settings must be a JSON object')
    cleaned = {}
    for field in fields(RenderOptions):
        key = field.name
        value = data.get(key, defaults[key])
        default = defaults[key]
        if isinstance(default, bool):
            cleaned[key] = value if type(value) is bool else default
        elif isinstance(default, int):
            cleaned[key] = value if type(value) is int else default
        elif isinstance(default, float):
            cleaned[key] = float(value) if type(value) in (int, float) else default
        else:
            cleaned[key] = value if isinstance(value, str) else default
    return RenderOptions(**cleaned)


def load_settings(path: Path = SETTINGS_FILE) -> RenderOptions:
    if not path.exists():
        # Migrate previous editions once. All future saves are project-local.
        if path == SETTINGS_FILE and LEGACY_SETTINGS_FILE.is_file():
            old = load_settings(LEGACY_SETTINGS_FILE)
            save_settings(old, SETTINGS_FILE)
            return old
        return RenderOptions()
    data = json.loads(path.read_text(encoding='utf-8'))
    if isinstance(data, dict):
        # Drop legacy saved titles, too: each new session starts with a blank title.
        data.pop('title', None)
    return from_mapping(data)


def save_settings(options: RenderOptions, path: Path = SETTINGS_FILE) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    # Atomic replacement avoids damaged settings if the app exits while writing.
    fd, temp = tempfile.mkstemp(prefix='.settings-', suffix='.json', dir=path.parent)
    try:
        with os.fdopen(fd, 'w', encoding='utf-8') as handle:
            values = asdict(options)
            values.pop('title', None)  # Post titles must not be persisted.
            values.pop('cover_enabled', None)  # Cover cannot be disabled.
            json.dump(values, handle, ensure_ascii=False, indent=2)
            handle.write('\n')
        os.replace(temp, path)
    finally:
        if os.path.exists(temp):
            os.unlink(temp)
