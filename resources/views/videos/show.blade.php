@extends('layouts.app')
@section('title','Video Details #'.$video->id)
@section('content')
@php
$currentRender=$video->currentRender;
$currentPublications=$currentRender?->publications ?? collect();
$canPublish=$video->status==='ready' && $currentRender?->status==='ready' && $currentPublications->isEmpty();
@endphp
<div class="page-intro">
 <div><span class="eyebrow">MEDIA / VIDEO {{ str_pad($video->id,4,'0',STR_PAD_LEFT) }}</span><h1>Video Details <span class="soft-dot">.</span></h1><p>{{ $video->channel?->label }} <span class="dot-sep">•</span> Message {{ $video->telegram_message_id }} <span class="dot-sep">•</span> {{ $video->created_at->format('Y/m/d H:i') }}</p></div>
 <div class="top-cta">
  <a class="btn btn-subtle" href="{{ route('videos.index') }}">Back</a>
  @if($currentRender && $video->status==='ready')<a class="btn btn-dark" href="{{ route('videos.download',$video) }}">Download MP4 <svg class="ico"><use href="#i-upload"/></svg></a>@endif
 </div>
</div>
@if($canPublish)
<div class="review-banner" aria-label="Publish ready video">
 <div class="review-banner-copy"><span class="eyebrow">READY FOR APPROVAL</span><strong>Ready to publish</strong><small>Review and save the caption, then send this version to enabled senders.</small></div>
 <div class="review-banner-actions">
  <a href="#caption-editor" class="btn btn-subtle">Edit caption</a>
  <form method="post" action="{{ route('videos.approve',$video) }}">@csrf
   <button class="btn btn-accent" type="submit"><svg class="ico"><use href="#i-telegram"/></svg> Approve &amp; Publish</button>
  </form>
 </div>
</div>
@endif
<div class="detail-grid"><div class="detail-main">
<section class="surface media-panel"><div class="box-heading"><div><span class="eyebrow">PREVIEW</span><h3>Output Preview</h3></div>@include('components.status',['status'=>$video->status])</div>
<div class="preview-area">
 @if($video->currentRender && $video->currentRender->status==='ready')
 <video controls playsinline preload="metadata" poster="{{ route('videos.file',[$video,'poster']) }}" src="{{ route('videos.file',[$video,'output']) }}"></video>
 @elseif($video->source_path)
 <video controls playsinline preload="metadata" src="{{ route('videos.file',[$video,'source']) }}"></video>
 @else <div class="preview-placeholder"><span>RDM</span><p>Video source has not been downloaded yet.</p>@endif</div>
</div>
@if(in_array($video->status,['downloading','processing','queued']))<div class="progress-caption"><span>Processing progress</span><strong>{{ $video->progress }}%</strong></div><div class="progress-track"><span style="width:{{ $video->progress }}%"></span></div><p class="dim tiny">Refresh this page to check the latest processing status.</p>@endif
@if($video->error)<div class="inline-error">{{ $video->error }}</div>@endif
</section>
<section class="surface" id="remake"><div class="box-heading"><div><span class="eyebrow">RE-EDIT</span><h3>Edit and Remake</h3></div><span class="section-tag">REMAKE</span></div>
<form method="post" action="{{ route('videos.remake',$video) }}">@csrf
<div class="field-group"><label class="field-label" for="title">Video title overlay</label><textarea dir="auto" id="title" name="title" class="field textarea" rows="3" maxlength="300" placeholder="Cover text">{{ old('title',$video->title) }}</textarea><small class="input-hint">Only the title and video settings affect Remake. Edit and save the caption separately in the review panel.</small></div>
<details class="accordion"><summary>Video-specific settings <span>↘</span></summary><div class="setting-fields">@foreach($schema as $key=>$spec)<div class="setting-item"><label for="s_{{ $key }}">{{ $spec[0] }}</label>
@if($spec[1]==='boolean')<input type="hidden" name="settings[{{ $key }}]" value="0"><label class="toggle-label"><input type="checkbox" id="s_{{ $key }}" name="settings[{{ $key }}]" value="1" @checked(old('settings.'.$key,$initial[$key]??false))><span></span>Enabled</label>
@elseif($spec[1]==='select')<select id="s_{{ $key }}" class="field" name="settings[{{ $key }}]">@foreach($spec[3] as $option)<option value="{{ $option }}" @selected((string)old('settings.'.$key,$initial[$key]??'')===(string)$option)>{{ $option }}</option>@endforeach</select>
@else<input id="s_{{ $key }}" class="field" type="{{ $spec[1]==='color'?'color':(in_array($spec[1],['integer','number'])?'number':'text') }}" @if($spec[1]==='number') step="any" @elseif($spec[1]==='integer') step="1" @endif name="settings[{{ $key }}]" value="{{ old('settings.'.$key,$initial[$key]??'') }}" @if(isset($spec[4])) min="{{ $spec[3] }}" max="{{ $spec[4] }}" @endif>@endif</div>
@endforeach</div></details>
<div class="form-footer"><span>Rebuild video with updated title/settings. Saved caption is kept.</span><button class="btn btn-dark" type="submit" @disabled(!in_array($video->status,['ready','failed']))>Create new version <svg class="ico"><use href="#i-spark"/></svg></button></div>
</form></section>
</div>
<aside class="detail-side"><section class="surface publish-panel" id="publish"><span class="eyebrow">REVIEW & PUBLISH</span><h3>Publication approval</h3><p class="subparagraph">Review the current video and caption before approving publication.</p>
@if($video->currentRender)
<div class="info-row"><span>Current version</span><strong dir="ltr">V{{ $video->currentRender->version }}</strong></div>
<div class="info-row"><span>Status</span>@include('components.status',['status'=>$video->currentRender->status])</div>
<div class="caption-preview" dir="auto" id="captionText">{{ $currentRender->caption }}</div>
<div class="caption-toolbar"><button class="btn btn-subtle" type="button" data-copy="captionText">Copy caption</button><span>Version V{{ $currentRender->version }}</span></div>
<div id="caption-editor" class="caption-editor">
 <div class="caption-editor-heading"><h4>Caption editor</h4><span class="section-tag">NO RE-RENDER</span></div>
 @if($canPublish)
 <form method="post" action="{{ route('videos.caption',$video) }}">@csrf
  <label for="caption_body" class="field-label">Caption text</label>
  <textarea dir="auto" id="caption_body" name="caption_body" class="field textarea" rows="6" maxlength="6500" placeholder="Write the text that will be published with this video.">{{ old('caption_body',$video->caption_body ?? $video->ocr_text ?? '') }}</textarea>
  <small class="input-hint">Global hashtags from App Settings are appended on save. The full Telegram caption must be 1,024 characters or less.</small>
  <button class="btn btn-dark wide" type="submit"><svg class="ico"><use href="#i-check"/></svg> Save caption</button>
 </form>
 @else
 <p class="caption-locked">Caption editing is available when a version is ready and not yet approved. Published or approved versions keep their original caption; make a new version to edit again.</p>
 @endif
</div>
@php $publications=$currentPublications; @endphp
@if($publications->isNotEmpty())
 @foreach($publications as $pub)
 <div class="publish-status"><b>{{ $pub->sender?->name ?? 'Legacy Sender' }}</b> @include('components.status',['status'=>$pub->status])
 <small>Destination: {{ $pub->destination }}</small>
 @if($pub->telegram_message_id)<small>Message ID: {{ $pub->telegram_message_id }}</small>@endif
 @if($pub->error)<p class="inline-error">{{ $pub->error }}</p>@endif
 @if($pub->status==='uncertain')<p class="dim tiny">The sender request may have succeeded. Check the destination before retrying.</p><a class="text-link" href="{{ route('queues.index',['tab'=>'deliveries','status'=>'uncertain']) }}">Review in Queue Monitor</a>@endif</div>
 @if(in_array($pub->status,['failed','uncertain']))
 <form method="post" action="{{ route('publications.retry',$pub) }}">@csrf
 @if($pub->status==='uncertain')<label class="confirm-row"><input type="checkbox" name="confirm_uncertain" value="1" required> I checked this sender destination and accept the risk of a duplicate post.</label>@endif
 <button class="btn btn-outline wide" type="submit">Retry {{ $pub->sender?->name ?? 'Sender' }}</button></form>
 @endif
 @endforeach
@else
<form method="post" action="{{ route('videos.approve',$video) }}">@csrf<button type="submit" class="btn btn-accent wide" @disabled($video->status!=='ready')>Approve &amp; publish to enabled senders <svg class="ico"><use href="#i-telegram"/></svg></button></form>
@endif
@else<p class="dim">Rendering must finish before the video can be published.</p>@endif
</section>
<section class="surface"><span class="eyebrow">ORIGINAL FRAME</span><h3>Persian OCR result</h3><pre class="ocr-block" dir="auto">{{ $video->ocr_text ?: 'No Persian text was detected in the first frame, or OCR has not run yet.' }}</pre><p class="dim tiny">OCR examines only the first video frame.</p></section>
<section class="surface"><span class="eyebrow">HISTORY</span><h3>Render history</h3>@forelse($video->renders as $version)<div class="version-row"><div><strong>V{{ $version->version }}</strong><small>{{ $version->created_at->format('Y/m/d H:i') }}</small></div>@include('components.status',['status'=>$version->status]) @if($version->status==='ready')<a class="icon-link" href="{{ route('videos.download',[$video,'version'=>$version->version]) }}">Download</a>@endif</div>@empty<div class="empty-inline">No versions have been created yet.</div>@endforelse
@if($video->status==='failed')<form method="post" action="{{ route('videos.retry',$video) }}">@csrf<button class="btn btn-outline wide" type="submit">Retry failed render</button></form>@endif
</section></aside></div>
@endsection
