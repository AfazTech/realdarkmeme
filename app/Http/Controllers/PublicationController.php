<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\{Video,Publication,ActivityLog};
use App\Jobs\PublishVideo;
use App\Support\BotSettings;
use Illuminate\Support\Facades\Storage;
class PublicationController extends Controller
{
 public function approve(Video $video){
    BotSettings::assertReady();
    $pub=DB::transaction(function()use($video){
        $v=Video::whereKey($video->id)->lockForUpdate()->firstOrFail();
        if($v->status!=='ready'||!$v->currentRender||$v->currentRender->status!=='ready')
          throw ValidationException::withMessages(['publish'=>'Only a ready version can be approved.']);
        $render=$v->currentRender;
        if(!$render->output_path||!Storage::disk('private')->exists($render->output_path))throw ValidationException::withMessages(['publish'=>'Output file is missing.']);
        if(mb_strlen($render->caption)>1024)throw ValidationException::withMessages(['publish'=>'The caption exceeds 1,024 characters. Shorten it in Remake.']);
        if(Publication::where('render_id',$render->id)->exists())throw ValidationException::withMessages(['publish'=>'This version has already been approved.']);
        $pub=Publication::create(['video_id'=>$v->id,'render_id'=>$render->id,'destination'=>BotSettings::chat(),'status'=>'queued','approved_by'=>auth()->id()]);
        PublishVideo::dispatch($pub->id)->onQueue('publish')->afterCommit();
        return $pub;
    });
    ActivityLog::record('publish.approved','Publication '.$pub->id,auth()->id());
    return back()->with('ok','Approved. The video and caption are queued for publishing.');
 }
 public function retry(Request $r,Publication $publication){
    $data=$r->validate(['confirm_uncertain'=>'sometimes|accepted']);
    DB::transaction(function()use($publication,$r){
        $p=Publication::whereKey($publication->id)->lockForUpdate()->firstOrFail();
        if(!in_array($p->status,['failed','uncertain'],true))throw ValidationException::withMessages(['publish'=>'This publication cannot be retried.']);
        if($p->status==='uncertain'&&!$r->boolean('confirm_uncertain'))throw ValidationException::withMessages(['publish'=>'Check the destination channel and confirm the risk of a duplicate post.']);
        $p->update(['status'=>'queued','error'=>null]);
        PublishVideo::dispatch($p->id)->onQueue('publish')->afterCommit();
    });
    return back()->with('ok','Publication queued for retry.');
 }
}
