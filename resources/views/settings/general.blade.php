@extends('layouts.app')
@section('title','App Settings')
@section('content')
<div class="page-intro"><div><span class="eyebrow">CONFIGURATION / APPLICATION</span><h1>App Settings <span class="soft-dot">.</span></h1><p>Default captions, Senders and administrator account security.</p></div></div>
<div class="settings-tabs"><a href="#captions">Captions</a><a href="#publishing">Senders</a><a href="#account">Admin account</a><a href="{{ route('settings.index') }}">Studio Settings</a></div>
<section class="surface settings-block" id="captions"><span class="eyebrow">CONTENT DEFAULTS</span><h3>Default hashtags</h3><p class="subparagraph">Hashtags are appended to newly generated captions. Existing render versions are not changed.</p>
<form method="post" action="{{ route('settings.hashtags') }}">@csrf<label class="field-label" for="hashtags">Hashtags</label><textarea class="field textarea" id="hashtags" name="hashtags" rows="3" maxlength="2000" placeholder="#realdarkmeme #memes">{{ old('hashtags',$hashtags) }}</textarea><small class="input-hint">Separate hashtags with spaces or commas. Maximum 30 distinct tags.</small><div class="form-footer"><span>The original caption text is preserved.</span><button class="btn btn-dark" type="submit">Save hashtags</button></div></form>
</section>
<section class="surface settings-block" id="publishing">
 <div class="box-heading"><div><span class="eyebrow">DELIVERY / CONNECTORS</span><h3>Senders</h3></div><span class="small-counter">{{ $senders->count() }} configured</span></div>
 <p class="subparagraph">Each enabled Sender receives a video when an admin approves its render. Telegram is the first connector; future services can be added separately. Disable a Sender to stop new deliveries without removing its history.</p>
 @foreach($senders as $sender)
 <div class="surface" style="margin:16px 0;padding:18px;border:1px solid var(--line, #e6e6e6)">
  <form method="post" action="{{ route('senders.update',$sender) }}">@csrf @method('PATCH')
   <div class="box-heading"><div><span class="eyebrow">{{ strtoupper($sender->type) }} / SENDER #{{ $sender->id }}</span><h3>{{ $sender->name }}</h3></div><label class="toggle-label"><input type="checkbox" name="enabled" value="1" @checked($sender->enabled) aria-label="Sender enabled"><span></span></label></div>
   <div class="field-grid">
    <div><label class="field-label">Sender name</label><input class="field" name="name" required maxlength="120" value="{{ $sender->name }}"></div>
    <div><label class="field-label">Destination channel</label><input class="field" name="destination" required dir="ltr" value="{{ $sender->destination }}"></div>
    <div><label class="field-label">Bot token</label><input class="field" name="token" type="password" autocomplete="new-password" placeholder="{{ $sender->token ? 'Saved · leave empty to keep' : 'Bot token required' }}"><small class="input-hint">Encrypted and never displayed.</small></div>
    <div><label class="field-label">Bot API URL</label><input class="field" name="api_url" dir="ltr" value="{{ $sender->api_url }}" required></div>
   </div><input type="hidden" name="type" value="{{ $sender->type }}">
   <div class="form-footer"><span>{{ $sender->enabled?'Enabled':'Disabled' }} · {{ $sender->publications()->count() }} deliveries</span><button class="btn btn-dark" type="submit">Save sender</button></div>
  </form>
  <form method="post" action="{{ route('senders.test',$sender) }}" class="test-form">@csrf<button class="btn btn-subtle" type="submit">Test bot connection</button></form>
 </div>
 @endforeach
 <div class="box-heading" style="margin-top:26px"><div><span class="eyebrow">NEW SENDER</span><h3>Add Telegram Sender</h3></div></div>
 <form method="post" action="{{ route('senders.store') }}">@csrf<input type="hidden" name="type" value="telegram">
  <div class="field-grid">
   <div><label class="field-label">Sender name</label><input class="field" name="name" maxlength="120" required placeholder="Telegram · Main Channel"></div>
   <div><label class="field-label">Destination channel</label><input class="field" name="destination" dir="ltr" required placeholder="@channel or -100..."></div>
   <div><label class="field-label">Bot token</label><input class="field" name="token" type="password" dir="ltr" required autocomplete="new-password"></div>
   <div><label class="field-label">Bot API URL</label><input class="field" name="api_url" dir="ltr" required value="{{ config('realdarkmeme.bot_api_url') }}"></div>
  </div><div class="form-footer"><span>Additional platforms can be registered as separate connector types later.</span><button class="btn btn-dark" type="submit">Add Sender</button></div>
 </form>
</section>
<section class="surface settings-block" id="account"><span class="eyebrow">ADMIN SECURITY</span><h3>Change admin password</h3><p class="subparagraph">The password cannot be empty. A long, unique password is recommended.</p>
<form method="post" action="{{ route('settings.password') }}">@csrf<div class="field-grid"><div><label class="field-label">Current password</label><input class="field" type="password" name="current_password" required autocomplete="current-password"></div><div><label class="field-label">New password</label><input class="field" type="password" name="password" required autocomplete="new-password"></div><div><label class="field-label">Confirm new password</label><input class="field" type="password" name="password_confirmation" required autocomplete="new-password"></div></div><div class="form-footer"><span>Your current session remains active after this change.</span><button type="submit" class="btn btn-dark">Change password</button></div></form>
</section>
@endsection
