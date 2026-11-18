@extends('layouts.app')
@section('title','Video Library')
@section('content')
<div class="page-intro"><div><span class="eyebrow">MEDIA / LIBRARY</span><h1>Video Library <span class="soft-dot">.</span></h1><p>Browse collected videos, render versions and processing status.</p></div><span class="small-counter">{{ number_format($videos->total()) }} videos</span></div>
<div class="filterbar"><a href="{{ route('videos.index') }}" class="filter {{ !$filter?'active':'' }}">All</a>@foreach(['ready'=>'Ready','processing'=>'Processing','queued'=>'Queued','downloading'=>'Downloading','failed'=>'Failed'] as $status=>$label)<a href="{{ route('videos.index',['status'=>$status]) }}" class="filter {{ $filter===$status?'active':'' }}">{{ $label }}</a>@endforeach</div>
@if($videos->isNotEmpty())<div class="video-grid">@foreach($videos as $video)@include('components.video-card',['video'=>$video])@endforeach</div><div class="pagination">{{ $videos->links('pagination.simple') }}</div>
@else<div class="empty-state"><span class="big-symbol">◫</span><h3>No matching videos</h3><p>Choose a different filter or check your source channels.</p><a href="{{ route('channels.index') }}" class="btn btn-dark">Source Channels</a></div>@endif
@endsection
