@extends('layouts.app')
@section('title','Dashboard')
@section('content')
<div class="page-intro"><div><span class="eyebrow">OVERVIEW / CONTENT OPERATIONS</span><h1>Welcome back <span class="soft-dot">.</span></h1><p>Your Telegram-to-video workflow, all in one workspace.</p></div><a class="btn btn-dark" href="{{ route('videos.index') }}">Open library <svg class="ico"><use href="#i-arrow"/></svg></a></div>
<div class="hero-grid"><section class="hero-card"><div class="hero-deco"><span>01</span><span>02</span><span>03</span></div><span class="hero-kicker"><i></i> REALDARKMEME STUDIO</span><h2>Collect content.<br>Make it. <em>Publish it.</em></h2><p>Collect videos from selected channels, render them, and publish after a final review.</p><a href="{{ route('channels.index') }}">Manage sources <svg class="ico"><use href="#i-arrow"/></svg></a></section>
<section class="metric-grid">
 <a href="{{ route('videos.index') }}" class="metric"><div class="metric-top">Total videos <svg class="ico"><use href="#i-play"/></svg></div><strong>{{ number_format($total) }}</strong><span>Videos in your library</span></a>
 <a href="{{ route('videos.index',['status'=>'ready']) }}" class="metric"><div class="metric-top">Ready for review <svg class="ico"><use href="#i-check"/></svg></div><strong>{{ number_format($ready) }}</strong><span>Rendered videos</span></a>
 <a href="{{ route('videos.index',['status'=>'processing']) }}" class="metric"><div class="metric-top">In progress <svg class="ico"><use href="#i-clock"/></svg></div><strong>{{ number_format($processing) }}</strong><span>Downloading and rendering</span></a>
 <a href="{{ route('settings.general') }}#publishing" class="metric"><div class="metric-top">Published posts <svg class="ico"><use href="#i-telegram"/></svg></div><strong>{{ number_format($published) }}</strong><span>Successfully delivered to your channel</span></a>
</section></div>
<div class="split-heading"><div><span class="eyebrow">CONTENT LIBRARY</span><h2>Recent videos</h2></div><a class="text-link" href="{{ route('videos.index') }}">View all <svg class="ico"><use href="#i-arrow"/></svg></a></div>
@if($videos->isNotEmpty())<div class="video-grid">@foreach($videos as $video) @include('components.video-card',['video'=>$video]) @endforeach</div>
@else<div class="empty-state"><span class="big-symbol">↙</span><h3>No videos yet</h3><p>Add a source channel and start the Telegram listener to collect new videos.</p><a href="{{ route('channels.index') }}" class="btn btn-dark">Manage channels</a></div>@endif
<div class="lower-grid"><section class="surface"><div class="box-heading"><div><span class="eyebrow">EVENT LOG</span><h3>Recent activity</h3></div></div>
@if($activities->isEmpty())<div class="empty-inline">No activity has been recorded yet.</div>@endif
@foreach($activities as $activity)<div class="activity"><span class="activity-point"></span><div><strong>{{ $activity->event }}</strong><small>{{ $activity->detail }}</small></div><time dir="ltr">{{ $activity->created_at->format('m/d H:i') }}</time></div>@endforeach</section>
<section class="surface quick-guide"><span class="eyebrow">WORKFLOW</span><h3>Publishing workflow</h3><div class="step"><span>01</span><div><b>Automatic collection</b><small>Only videos from enabled channels</small></div></div><div class="step"><span>02</span><div><b>OCR & rendering</b><small>First-frame Persian OCR, cover and outro</small></div></div><div class="step"><span>03</span><div><b>Review & Remake</b><small>Edit and regenerate any video</small></div></div><div class="step"><span>04</span><div><b>Approve & publish</b><small>Send using the Neili bot</small></div></div></section></div>
@endsection
