<?php
namespace App\Services;

use App\Models\{Video,Render,ActivityLog};
use App\Support\RenderSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use RuntimeException;
use Throwable;

final class VideoPipeline
{
    private function exec(array $args, ?int $timeout=90): string
    {
        $proc=new Process($args,base_path());$proc->setTimeout($timeout);$proc->run();
        if(!$proc->isSuccessful())throw new RuntimeException(mb_substr($proc->getErrorOutput(),0,1200)?:'External process failed');
        return $proc->getOutput();
    }
    public static function onlyPersian(string $raw): string
    {
        $lines=[];
        foreach(preg_split('/\R/u',$raw)?:[] as $line) {
            $line=preg_replace('/[^\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}0-9 \t،؛؟!:.()\-]/u','',$line)??'';
            $line=preg_replace('/\s+/u',' ',trim($line))??'';
            if(preg_match_all('/[\x{0621}-\x{064A}\x{067E}\x{0686}\x{0698}\x{06A9}\x{06AF}\x{06CC}]/u',$line)>=2)$lines[]=$line;
        }
        return mb_substr(implode("\n",array_slice($lines,0,8)),0,1000);
    }
    public function run(Video $video): void
    {
        $disk=Storage::disk('private');
        $video->refresh();
        if($video->status!=='queued')return;
        $video->update(['status'=>'processing','progress'=>1,'error'=>null]);
        $render=null;
        $jobPath=null;
        try {
            if(!$video->source_path || !$disk->exists($video->source_path))throw new RuntimeException('Original video file is missing');
            $input=$disk->path($video->source_path);
            $frame='frames/'.$video->id.'.png';
            if(!$disk->exists($frame)) {
                $this->exec([config('realdarkmeme.ffmpeg'),'-hide_banner','-loglevel','error','-y','-i',$input,'-frames:v','1',$disk->path($frame)],120);
                if(!$disk->exists($frame))throw new RuntimeException('First frame was not created');
            }
            $video->update(['first_frame_path'=>$frame,'progress'=>5]);
            if($video->ocr_text===null) {
                $raw=$this->exec([config('realdarkmeme.tesseract'),$disk->path($frame),'stdout','-l','fas','--psm','6']);
                $video->ocr_text=self::onlyPersian($raw);
                $video->save();
            }
            $title=$video->title ?? mb_substr((string)$video->ocr_text,0,72);
            $body=$video->caption_body ?? (string)$video->ocr_text;
            $caption=RenderSettings::caption($body);
            $settings=array_replace(RenderSettings::current(),array_intersect_key($video->overrides??[],RenderSettings::schema()));
            $version=($video->renders()->max('version')??0)+1;
            $out='outputs/'.$video->id.'/v'.$version.'.mp4';
            $poster='outputs/'.$video->id.'/v'.$version.'.jpg';
            $disk->makeDirectory('outputs/'.$video->id);
            $render=Render::query()->create([
                'video_id'=>$video->id,'version'=>$version,'status'=>'processing','output_path'=>$out,'poster_path'=>$poster,
                'title'=>$title,'caption'=>$caption,'settings_snapshot'=>$settings,
            ]);
            $jobPath='jobs/'.$video->id.'-'.$render->id.'-'.bin2hex(random_bytes(4)).'.json';
            $jobSettings=$settings+[
                'input_video'=>$input, 'cover_image'=>$disk->path('assets/thumbnail.png'),
                'outro_image'=>$disk->path('assets/outro.png'),'output_video'=>$disk->path($out),
            ];
            $disk->put($jobPath,json_encode(['title'=>$title,'settings'=>$jobSettings],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            $proc=new Process([config('realdarkmeme.python'),base_path('engine/render_job.py'),$disk->path($jobPath)],base_path('engine'));
            $proc->setTimeout((int)config('realdarkmeme.render_timeout',3600));
            $log=storage_path('logs/render-'.$video->id.'-v'.$version.'.log');
            $buffer='';$last=5;
            $proc->run(function($type,$chunk) use ($video,$log,&$buffer,&$last){
                file_put_contents($log,$chunk,FILE_APPEND);
                if($type!==Process::OUT)return;
                $buffer.=$chunk;
                while(($pos=strpos($buffer,"\n"))!==false) {
                    $line=substr($buffer,0,$pos);$buffer=substr($buffer,$pos+1);$data=json_decode($line,true);
                    if(is_array($data)&&isset($data['progress'])) {
                        $p=max(5,min(99,(int)$data['progress']));
                        if($p>=$last+3){$video->update(['progress'=>$p]);$last=$p;}
                    }
                }
            });
            if(!$proc->isSuccessful())throw new RuntimeException('Render failed; see '.basename($log));
            if(!$disk->exists($out)||$disk->size($out)<1024)throw new RuntimeException('Renderer produced no MP4');
            try {$this->exec([config('realdarkmeme.ffmpeg'),'-hide_banner','-loglevel','error','-y','-i',$disk->path($out),'-frames:v','1','-q:v','3',$disk->path($poster)],120);}catch(Throwable){ $poster=null; }
            DB::transaction(function()use($video,$render,$title,$body,$caption,$poster){
                $render->update(['status'=>'ready','poster_path'=>$poster,'completed_at'=>now()]);
                $video->update(['status'=>'ready','progress'=>100,'title'=>$title,'caption_body'=>$body,'caption'=>$caption,'current_render_id'=>$render->id,'error'=>null]);
            });
            ActivityLog::record('render.ready','Video '.$video->id.' / version '.$version);
        } catch(Throwable $e) {
            if($render)$render->update(['status'=>'failed','error'=>mb_substr($e->getMessage(),0,1000),'completed_at'=>now()]);
            $video->update(['status'=>'failed','error'=>mb_substr($e->getMessage(),0,1000),'progress'=>0]);
            ActivityLog::record('render.failed','Video '.$video->id.': '.mb_substr($e->getMessage(),0,500));
        } finally {
            if($jobPath)$disk->delete($jobPath);
        }
    }
}
