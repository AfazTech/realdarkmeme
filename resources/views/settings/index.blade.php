@extends('layouts.app')
@section('title','Studio Settings')
@section('content')
<div class="page-intro"><div><span class="eyebrow">STUDIO / RENDERING</span><h1>Studio Settings <span class="soft-dot">.</span></h1><p>Customize video rendering, cover design, watermark and outro assets.</p></div></div>
<div class="settings-tabs"><a href="#rendering">Render options</a><a href="#assets">Media assets</a><a href="{{ route('settings.general') }}">App Settings <svg class="ico"><use href="#i-arrow"/></svg></a></div>
<section class="surface settings-block" id="rendering"><div class="box-heading"><div><span class="eyebrow">RENDER ENGINE</span><h3>Video processing defaults</h3></div><span class="section-tag">FFMPEG / PYTHON</span></div>
<p class="subparagraph">These defaults apply to new renders. You can override them for an individual video when using Remake.</p>
<form method="post" action="{{ route('settings.render') }}">@csrf
@foreach(['Cover','Watermark','Video','Outro','Output'] as $group)
<div class="settings-section"><div class="group-title"><span>{{ sprintf('%02d',$loop->iteration) }}</span><h4>{{ $group }}</h4></div><div class="setting-fields">
@foreach($schema as $key=>$spec)@if($spec[2]===$group)
<div class="setting-item"><label for="s_{{ $key }}">{{ $spec[0] }}</label>
@if($spec[1]==='boolean')<label class="toggle-label"><input type="checkbox" id="s_{{ $key }}" name="settings[{{ $key }}]" value="1" @checked(old('settings.'.$key,$render[$key]??false))><span></span>Enabled</label>
@elseif($spec[1]==='select')<select id="s_{{ $key }}" class="field" name="settings[{{ $key }}]">@foreach($spec[3] as $option)<option value="{{ $option }}" @selected((string)old('settings.'.$key,$render[$key]??'')===(string)$option)>{{ $option }}</option>@endforeach</select>
@else<input id="s_{{ $key }}" class="field" name="settings[{{ $key }}]" type="{{ $spec[1]==='color'?'color':(in_array($spec[1],['integer','number'])?'number':'text') }}" @if($spec[1]==='integer') step="1" @elseif($spec[1]==='number') step="any" @endif value="{{ old('settings.'.$key,$render[$key]??'') }}" @if(isset($spec[4])) min="{{ $spec[3] }}" max="{{ $spec[4] }}" @endif>@endif</div>
@endif @endforeach
</div></div>@endforeach
<div class="form-footer"><span>Previously rendered videos remain unchanged.</span><button class="btn btn-dark" type="submit">Save render settings <svg class="ico"><use href="#i-check"/></svg></button></div></form></section>
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
