@extends('layouts.app')
@section('title','Media Assets')
@section('content')
<div class="page-intro"><div><span class="eyebrow">STUDIO / MEDIA</span><h1>Media Assets <span class="soft-dot">.</span></h1><p>Preview and replace the default cover and outro images.</p></div></div>
<div class="settings-tabs"><a href="{{ route('settings.index') }}">Studio Settings</a><a href="{{ route('settings.group','cover') }}">Cover</a><a href="{{ route('settings.group','outro') }}">Outro</a></div>
<section class="surface settings-block" id="assets"><span class="eyebrow">DESIGN ASSETS</span><h3>Cover and outro images</h3><p class="subparagraph">PNG files only. Existing images remain in place until replaced.</p>
<form method="post" action="{{ route('settings.assets') }}" enctype="multipart/form-data">@csrf
<div class="asset-preview-grid">
@foreach(['thumbnail'=>['Default cover','thumbnail.png'], 'outro'=>['Outro image','outro.png']] as $key=>$meta)
@php($asset=$mediaAssets[$key])
<div class="asset-preview-card">
 <div class="asset-preview-header"><strong>{{ $meta[0] }}</strong><span class="asset-state {{ $asset['exists'] ? 'asset-state-ready' : '' }}">{{ $asset['exists'] ? 'Configured' : 'Missing' }}</span></div>
 <div class="asset-preview-stage">
 @if($asset['exists'])<img src="{{ route('settings.assets.preview',['kind'=>$key,'v'=>$asset['updated']]) }}" alt="Current {{ strtolower($meta[0]) }}" loading="lazy">
 @else<div class="asset-preview-missing">No image configured yet</div>@endif
 </div>
 <div class="asset-preview-details"><span>{{ $meta[1] }}</span>
 @if($asset['exists'])<span>{{ number_format($asset['size']/1024,1) }} KB · Updated {{ date('M j, Y H:i',$asset['updated']) }}</span>@else<span>Run php artisan rdm:prepare to restore the default image.</span>@endif
 </div>
 <label class="file-drop asset-replace"><strong>Replace {{ strtolower($meta[0]) }}</strong><span>Choose a PNG file · max 10 MB</span><input type="file" accept="image/png" name="{{ $key }}"></label>
</div>
@endforeach
</div>
<div class="form-footer"><span>Current images are shown above. Uploads are stored privately, outside the web root.</span><button type="submit" class="btn btn-dark">Save image changes</button></div></form></section>
@endsection
