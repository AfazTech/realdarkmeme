<?php
namespace App\Support;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class RenderSettings
{
    public static function defaults(): array { return json_decode(file_get_contents(config_path('render_defaults.json')),true,512,JSON_THROW_ON_ERROR); }
    public static function current(): array
    {
        $raw=Setting::fetch('render_settings'); $updated=$raw ? json_decode($raw,true) : [];
        return array_replace(self::defaults(),is_array($updated)?array_intersect_key($updated,self::defaults()):[]);
    }
    public static function schema(): array
    {
        return [
            'auto_font_size'=>['Auto title font size','boolean','Cover'],
            'font_size'=>['Title font size','integer','Cover',12,300],
            'title_font'=>['Persian font path','text','Cover'], 'emoji_font'=>['Emoji font path','text','Cover'],
            'title_color'=>['Title color','color','Cover'], 'panel_width'=>['Title panel width (%)','integer','Cover',55,100],
            'title_x'=>['Horizontal position (%)','number','Cover',0,100], 'title_y'=>['Vertical position (%)','number','Cover',0,100],
            'title_width'=>['Text width (%)','integer','Cover',1,100], 'title_max_lines'=>['Maximum title lines','integer','Cover',1,8],
            'watermark_enabled'=>['Enable watermark','boolean','Watermark'], 'watermark_text'=>['Watermark text','text','Watermark'],
            'watermark_font'=>['Watermark font path','text','Watermark'], 'watermark_size'=>['Watermark size','integer','Watermark',8,120],
            'watermark_opacity'=>['Opacity (%)','integer','Watermark',0,100], 'watermark_border'=>['Outline thickness','integer','Watermark',0,12],
            'watermark_speed_x'=>['Horizontal motion speed','integer','Watermark',0,1000], 'watermark_speed_y'=>['Vertical motion speed','integer','Watermark',0,1000],
            'watermark_color'=>['Watermark color','color','Watermark'],'watermark_outline'=>['Watermark outline color','color','Watermark'],
            'background_blur'=>['Background blur','integer','Video',8,60], 'outro_enabled'=>['Enable outro','boolean','Outro'],
            'outro_seconds'=>['Outro duration (seconds)','number','Outro',0.1,30],
            'outro_fit'=>['Outro fit mode','select','Outro',['contain','cover','stretch']], 'outro_background'=>['Outro background color','color','Outro'],
            'crf'=>['CRF quality','integer','Output',0,51],
            'preset'=>['Encoding preset','select','Output',['ultrafast','superfast','veryfast','faster','fast','medium','slow','slower']],
            'output_fps'=>['Output FPS','select','Output',['0','24','25','30','50','60']],
            'audio_mode'=>['Audio mode','select','Output',['aac','copy','mute']], 'audio_bitrate'=>['Audio bitrate (kbps)','integer','Output',32,320],
        ];
    }
    public static function validate(array $data): array
    {
        $out=[];
        foreach(self::schema() as $key=>$def) {
            $val=$data[$key]??null;
            if ($def[1]==='boolean') { $out[$key]=in_array($val,[true,'1',1,'on'],true); continue; }
            // Laravel converts empty HTML inputs to null. Blank optional text values
            // (notably font paths) are valid and should use the renderer defaults.
            if ($def[1]==='text' && $val===null) $val='';
            if (!is_scalar($val)) throw ValidationException::withMessages([$key=>'Invalid value for '.$def[0].' ('.$key.').']);
            if ($def[1]==='integer' || $def[1]==='number') {
                if(!is_numeric($val)) throw ValidationException::withMessages([$key=>'Enter a valid number for '.$def[0].'.']);
                $v=$def[1]==='integer'?(int)$val:(float)$val;
                if ($def[1]==='integer' && (string)(int)$val !== (string)$val) throw ValidationException::withMessages([$key=>$def[0].' must be a whole number.']);
                if ($v<$def[3] || $v>$def[4]) throw ValidationException::withMessages([$key=>$def[0].' must be between '.$def[3].' and '.$def[4].'.']);
                $out[$key]=$v; continue;
            }
            if ($def[1]==='select') {
                if(!in_array((string)$val,$def[3],true))throw ValidationException::withMessages([$key=>'Choose a valid option for '.$def[0].'.']);
                $out[$key]=$key==='output_fps'?(int)$val:(string)$val;continue;
            }
            if($def[1]==='color' && !preg_match('/^#[A-Fa-f0-9]{6}$/',(string)$val))throw ValidationException::withMessages([$key=>'Enter a valid hex color for '.$def[0].'.']);
            if(mb_strlen((string)$val)>500)throw ValidationException::withMessages([$key=>$def[0].' is too long.']);
            $out[$key]=trim((string)$val);
        }
        return $out;
    }
    public static function hashtags(string $input): string
    {
        $out=[];
        foreach (preg_split('/[\s,،]+/u',$input)?:[] as $tag) {
            $tag=ltrim(trim($tag),'#');
            if($tag!=='' && preg_match('/^[\p{L}\p{N}_]{1,50}$/u',$tag))$out['#'.$tag]=true;
            if(count($out)>=30)break;
        }
        return implode(' ',array_keys($out));
    }
    public static function caption(string $body): string
    {
        $tags=self::hashtags((string)Setting::fetch('hashtags',''));
        return trim($body).(trim($body)!=='' && $tags!==''?"\n\n":'').$tags;
    }
}
