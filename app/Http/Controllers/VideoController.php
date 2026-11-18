<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
use App\Models\{Video,Render,ActivityLog};
use App\Jobs\ProcessVideo;
use App\Support\RenderSettings;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
class VideoController extends Controller
{
 public function index(Request $r){
    $q=Video::query()->with(['channel','currentRender.publications.sender'])->latest();
    if(in_array($r->query('status'),['ready','queued','processing','downloading','failed'],true))$q->where('status',$r->query('status'));
    if($r->filled('channel_id'))$q->where('channel_id',(int)$r->query('channel_id'));
    return view('videos.index',['videos'=>$q->paginate(18)->withQueryString(),'filter'=>$r->query('status','')]);
 }
 public function show(Video $video){
    $video->load(['channel','renders.publications.sender','currentRender.publications']);
    return view('videos.show',['video'=>$video,'schema'=>RenderSettings::schema(),'global'=>RenderSettings::current(),'initial'=>array_replace(RenderSettings::current(),$video->overrides??[])]);
 }
 /**
  * Editing a caption does not change the video overlay or start a render.
  * Approved versions are immutable: sender deliveries must retain their snapshot.
  */
 public function caption(Request $request, Video $video)
 {
     $data=$request->validate(['caption_body'=>'nullable|string|max:6500']);
     $body=trim((string)($data['caption_body']??''));
     $caption=RenderSettings::caption($body);
     if(mb_strlen($caption)>1024)
         throw ValidationException::withMessages(['caption_body'=>'Caption and hashtags exceed the Telegram limit of 1,024 characters.']);
     DB::transaction(function()use($video,$body,$caption) {
         $locked=Video::query()->whereKey($video->id)->lockForUpdate()->firstOrFail();
         $render=$locked->currentRender;
         if($locked->status!=='ready'||!$render||$render->status!=='ready')
             throw ValidationException::withMessages(['caption_body'=>'Wait for the current render to finish before editing its caption.']);
         if($render->publications()->exists())
             throw ValidationException::withMessages(['caption_body'=>'This version is already approved. Create a new version to edit its caption.']);
         $render->update(['caption'=>$caption]);
         $locked->update(['caption_body'=>$body,'caption'=>$caption]);
     });
     ActivityLog::record('video.caption.updated','Video '.$video->id,auth()->id());
     return back()->with('ok','Caption saved. The video was not re-rendered.');
 }

 public function remake(Request $r,Video $video){
    // A title-only Remake must not depend on values from a collapsed settings panel.
    $v=$r->validate(['title'=>'nullable|string|max:300','settings'=>'sometimes|array']);
    $base=RenderSettings::current();
    $current=array_replace($base,array_intersect_key($video->overrides??[],$base));
    $submitted=$r->input('settings',[]);
    $clean=RenderSettings::validate(array_replace($current,is_array($submitted)?$submitted:[]));
    $overrides=[];
    foreach($clean as $key=>$val)if($val!==($base[$key]??null))$overrides[$key]=$val;
    DB::transaction(function()use($video,$v,$overrides,$r){
       $locked=Video::whereKey($video->id)->lockForUpdate()->firstOrFail();
       if(!in_array($locked->status,['ready','failed'],true)||!$locked->source_path||!Storage::disk('private')->exists($locked->source_path))
           throw ValidationException::withMessages(['video'=>'The original video must exist and the previous render must be complete.']);
       $title=$r->exists('title')?trim((string)($v['title']??'')):$locked->title;
       // caption_body remains untouched: captions are edited and saved separately.
       $locked->update(['title'=>$title,'overrides'=>$overrides,'status'=>'queued','progress'=>0,'error'=>null]);
       ProcessVideo::dispatch($locked->id)->onQueue('render')->afterCommit();
    });
    ActivityLog::record('video.remake','Video '.$video->id,auth()->id());
    return back()->with('ok','New version queued. Your saved caption is preserved and publication requires approval.');
 }
 public function retry(Video $video){
    if($video->status!=='failed'||!$video->source_path)throw ValidationException::withMessages(['video'=>'Only failed renders can be retried.']);
    $video->update(['status'=>'queued','error'=>null,'progress'=>0]);ProcessVideo::dispatch($video->id)->onQueue('render');
    return back()->with('ok','Rendering queued again.');
 }
 private function resolve(Video $video,string $kind,Request $r): array
 {
    $disk=Storage::disk('private');
    if($kind==='source')$path=$video->source_path;
    else {
        $render=$r->filled('version')?$video->renders()->where('version',(int)$r->query('version'))->first():$video->currentRender;
        abort_unless($render&&$render->status==='ready',404);
        $path=$kind==='poster'?$render->poster_path:$render->output_path;
    }
    abort_unless($path && $disk->exists($path),404);
    return [$disk->path($path),$kind==='poster'?'image/jpeg':($kind==='source'?(mime_content_type($disk->path($path))?:'application/octet-stream'):'video/mp4')];
 }
 public function file(Request $r,Video $video,string $kind){
    [$path,$type]=$this->resolve($video,$kind,$r);
    return response()->file($path,['Content-Type'=>$type,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
 public function download(Request $r,Video $video){
    [$path]=$this->resolve($video,'output',$r);
    return response()->download($path,'realdarkmeme-'.$video->id.'-'.($r->query('version')?:'latest').'.mp4',['Cache-Control'=>'private, no-store']);
 }
}
