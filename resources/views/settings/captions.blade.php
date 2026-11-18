@extends('layouts.app')
@section('title','Caption Defaults')
@section('content')
<div class="page-intro"><div><span class="eyebrow">APPLICATION / CONTENT</span><h1>Caption Defaults <span class="soft-dot">.</span></h1><p>Manage the default hashtags appended to new captions.</p></div></div>
<div class="settings-tabs"><a href="{{ route('settings.general') }}">App Settings</a><a href="{{ route('senders.index') }}">Senders</a></div>
<section class="surface settings-block" id="captions"><span class="eyebrow">CONTENT DEFAULTS</span><h3>Default hashtags</h3><p class="subparagraph">Hashtags are appended to newly generated captions. Existing render versions are not changed.</p>
<form method="post" action="{{ route('settings.hashtags') }}">@csrf<label class="field-label" for="hashtags">Hashtags</label><textarea class="field textarea" id="hashtags" name="hashtags" rows="3" maxlength="2000" placeholder="#realdarkmeme #memes">{{ old('hashtags',$hashtags) }}</textarea><small class="input-hint">Separate hashtags with spaces or commas. Maximum 30 distinct tags.</small><div class="form-footer"><span>The original caption text is preserved.</span><button class="btn btn-dark" type="submit">Save hashtags</button></div></form>
</section>
@endsection
