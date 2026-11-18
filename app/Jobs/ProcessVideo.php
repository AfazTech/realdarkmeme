<?php
namespace App\Jobs;
use App\Models\Video;
use App\Services\VideoPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class ProcessVideo implements ShouldQueue
{
 use Queueable;
 public int $tries=1;
 public int $timeout=3600;
 public function __construct(public int $videoId) { $this->onQueue('render'); }
 public function handle(VideoPipeline $pipeline): void
 {
     $lock=storage_path('app/private/jobs/video-'.$this->videoId.'.lock');
     $fh=fopen($lock,'c'); if(!$fh)return;
     if(!flock($fh,LOCK_EX|LOCK_NB)){fclose($fh);return;}
     try { $video=Video::find($this->videoId); if($video)$pipeline->run($video); }
     finally {flock($fh,LOCK_UN);fclose($fh);}
 }
}
