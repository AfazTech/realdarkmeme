@extends('layouts.app')
@section('title','Studio Settings')
@section('content')
<div class="page-intro"><div><span class="eyebrow">STUDIO / CONFIGURATION</span><h1>Studio Settings <span class="soft-dot">.</span></h1><p>Choose a category to adjust its settings without modifying other rendering options.</p></div></div>
<div class="settings-home-grid">
 @foreach($groups as $key=>$name)
 <a class="settings-home-card" href="{{ route('settings.group',$key) }}"><span class="eyebrow">RENDERING · {{ sprintf('%02d',$loop->iteration) }}</span><h3>{{ $name }} settings</h3>
 <p>@switch($key)
 @case('cover')Title placement, font sizes, image overlay and Persian typography.@break
 @case('watermark')Watermark text, movement, colors and outline.@break
 @case('video')Blur and video treatment.@break
 @case('outro')Outro duration, image fitting and background.@break
 @default Output video quality, frame rate and audio options.
 @endswitch</p><span class="settings-card-arrow">Open settings →</span></a>
 @endforeach
 <a class="settings-home-card" href="{{ route('settings.media') }}"><span class="eyebrow">IMAGE LIBRARY</span><h3>Media Assets</h3><p>Current cover and outro images, previews and uploads.</p><span class="settings-card-arrow">Manage media →</span></a>
</div>
@endsection
