<?php
namespace App\Console\Commands;
use App\Models\{Video,Render};
use Illuminate\Console\Command;
class StudioRecoverRenders extends Command
{
 protected $signature='rdm:recover-renders';
 protected $description='Mark stale rendering and downloading items as failed for manual review';
 public function handle(): int
 {
   $stale=now()->subHours(2);
   $videoCount=Video::query()->whereIn('status',['processing','downloading'])->where('updated_at','<',$stale)->update([
     'status'=>'failed','error'=>'Interrupted task; check worker logs and retry manually','progress'=>0,
   ]);
   $renderCount=Render::query()->where('status','processing')->where('updated_at','<',$stale)->update([
     'status'=>'failed','error'=>'Interrupted rendering job; review before retry',
   ]);
   $this->info('Stale videos: '.$videoCount.'; stale renders: '.$renderCount);
   return self::SUCCESS;
 }
}
