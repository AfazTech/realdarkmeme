@extends('layouts.app')
@section('title',$groupName.' Settings')
@section('content')
<div class="page-intro"><div><span class="eyebrow">STUDIO / RENDERING</span><h1>{{ $groupName }} Settings <span class="soft-dot">.</span></h1><p>Changes to this section do not reset settings in other sections.</p></div></div>
<div class="settings-tabs"><a href="{{ route('settings.index') }}">Overview</a>
@foreach($groups as $slug=>$name)<a class="{{ $group===$slug?'active':'' }}" href="{{ route('settings.group',$slug) }}">{{ $name }}</a>@endforeach
<a href="{{ route('settings.media') }}">Media Assets</a></div>
<section class="surface settings-block"><div class="box-heading"><div><span class="eyebrow">RENDER DEFAULTS</span><h3>{{ $groupName }} configuration</h3></div><span class="section-tag">GLOBAL</span></div>
<p class="subparagraph">Saved defaults are used by new renders. Already rendered versions remain unchanged.</p>
<form method="post" action="{{ route('settings.group.save',$group) }}">@csrf
<div class="setting-fields">
@foreach($schema as $key=>$spec)<div class="setting-item"><label for="s_{{ $key }}">{{ $spec[0] }}</label>
@if($spec[1]==='boolean')<input type="hidden" name="settings[{{ $key }}]" value="0"><label class="toggle-label"><input type="checkbox" id="s_{{ $key }}" name="settings[{{ $key }}]" value="1" @checked(old('settings.'.$key,$render[$key]??false))><span></span>Enabled</label>
@elseif($spec[1]==='select')<select id="s_{{ $key }}" class="field" name="settings[{{ $key }}]">@foreach($spec[3] as $option)<option value="{{ $option }}" @selected((string)old('settings.'.$key,$render[$key]??'')===(string)$option)>{{ $option }}</option>@endforeach</select>
@else<input id="s_{{ $key }}" class="field" name="settings[{{ $key }}]" type="{{ $spec[1]==='color'?'color':(in_array($spec[1],['integer','number'])?'number':'text') }}" @if($spec[1]==='integer') step="1" @elseif($spec[1]==='number') step="any" @endif value="{{ old('settings.'.$key,$render[$key]??'') }}" @if(isset($spec[4])) min="{{ $spec[3] }}" max="{{ $spec[4] }}" @endif>@endif</div>
@endforeach</div>
<div class="form-footer"><span>Only {{ $groupName }} settings will be changed.</span><button class="btn btn-dark" type="submit">Save {{ $groupName }} settings <svg class="ico"><use href="#i-check"/></svg></button></div>
</form>
</section>
@endsection
