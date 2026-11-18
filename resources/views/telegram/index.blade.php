@extends('layouts.app')
@section('title','Telegram Account')
@section('content')
<div class="page-intro"><div><span class="eyebrow">TELEGRAM / SOURCE ACCOUNT</span><h1>Telegram Account <span class="soft-dot">.</span></h1><p>Connect the user account used by MadelineProto to monitor your source channels.</p></div></div>
<div class="two-col"><section class="surface">
  <div class="box-heading"><div><span class="eyebrow">ACCOUNT CONNECTION</span><h3>Sign in from the studio</h3></div>
  <details class="tg-api-config" @if(!$credentials_set) open @endif>
    <summary><strong>Telegram API credentials</strong> <span>{{ $credentials_set ? 'Configured' : 'Required before first sign-in' }}</span></summary>
    <p class="subparagraph">Create an API ID and hash at <a href="https://my.telegram.org" target="_blank" rel="noopener noreferrer" class="text-link">my.telegram.org</a>. These are the Telegram application credentials, not the publishing bot token.</p>
    <form method="post" action="{{ route('telegram.credentials') }}">@csrf
      <div class="field-grid"><div><label class="field-label" for="tg_api_id">API ID</label><input class="field" id="tg_api_id" name="api_id" type="number" min="1000" step="1" value="{{ old('api_id',$api_id) }}" required></div>
      <div><label class="field-label" for="tg_api_hash">API hash</label><input class="field" id="tg_api_hash" name="api_hash" type="password" autocomplete="new-password" placeholder="{{ $credentials_set ? 'Leave blank to keep the saved hash' : '32-character API hash' }}"></div></div>
      <div class="form-footer"><span>The API hash is encrypted and never displayed again.</span><button class="btn btn-subtle" type="submit" @disabled($busy)>Save API credentials</button></div>
    </form>
  </details>
  @if($state==='connected')<span class="status success"><i></i>Connected</span>@elseif(in_array($state,['code','password']))<span class="status info"><i></i>Verification required</span>@elseif($exists)<span class="status warn"><i></i>Session detected</span>@else<span class="status muted"><i></i>Not connected</span>@endif</div>
  @if($busy)
   <div class="telegram-operation" id="telegramOperation" data-status-url="{{ route('telegram.status') }}" role="status" aria-live="polite">
    <span class="telegram-spinner" aria-hidden="true"></span>
    <div><strong>Telegram operation {{ $job_state === 'queued' ? 'queued' : 'running' }}</strong>
    <p>{{ $operation_message ?: 'Waiting for Telegram...' }}</p><small id="telegramWorkerHint">This page stays responsive while the Telegram worker handles the request.</small></div>
   </div>
  @endif
  @if($error)<div class="inline-error">{{ $error }}</div>@endif
  @if($exists && !$busy)
   <form class="telegram-status-check" method="post" action="{{ route('telegram.refresh') }}">@csrf
    <button class="btn btn-outline" type="submit">Check session status</button>
    <small>Run a fresh check only when the listener is stopped. The check runs in the background.</small>
   </form>
  @endif
  @if($state==='connected')
   <div class="telegram-identity"><div class="telegram-avatar">{{ mb_strtoupper(mb_substr(trim(($me['first_name']??''). ' '.($me['last_name']??'')) ?: 'T',0,1)) }}</div><div><strong>{{ trim(($me['first_name']??'').' '.($me['last_name']??'')) ?: 'Telegram user' }}</strong><small>{{ !empty($me['username']) ? '@'.$me['username'] : 'No public username' }}</small></div></div>
   <div class="info-row"><span>Account status</span><strong>Authorized</strong></div>
   <div class="info-row"><span>Session storage</span><strong>Private server storage</strong></div>
   <div class="helper-note"><b>Important</b><p>Stop <code>php artisan rdm:listen</code> before disconnecting or changing the account. Restart the listener after connecting a new account.</p></div>
   <form class="disconnect-form" method="post" action="{{ route('telegram.disconnect') }}" onsubmit="return confirm('This will log out the Telegram account. Have you stopped the listener?')">@csrf<label class="confirm-row"><input type="checkbox" name="confirm" value="1" required> I stopped the listener and want to disconnect.</label><button class="btn btn-outline" type="submit" @disabled($busy)>Disconnect account</button></form>
  @elseif($state==='code')
   <div class="step-current"><span class="step-badge">02 / 03</span><h4>Enter verification code</h4><p>Check the Telegram app on your phone for the code.</p></div>
   <form method="post" action="{{ route('telegram.login.code') }}">@csrf<label class="field-label" for="tg_code">Login code</label><input class="field" type="text" id="tg_code" name="code" inputmode="numeric" pattern="[0-9]{3,10}" maxlength="10" autocomplete="one-time-code" required autofocus placeholder="12345"><button class="btn btn-dark wide" type="submit" @disabled($busy)>Verify code</button></form>
  @elseif($state==='password')
   <div class="step-current"><span class="step-badge">03 / 03</span><h4>Two-step verification</h4><p>Enter the Telegram account's two-step verification password. This password is never stored by the studio.</p></div>
   <form method="post" action="{{ route('telegram.login.password') }}">@csrf<label class="field-label" for="tg_password">Two-step password</label><input class="field" type="password" id="tg_password" name="telegram_password" required autofocus autocomplete="off"><button class="btn btn-dark wide" type="submit" @disabled($busy)>Complete sign in</button></form>
  @elseif($state==='unknown' && $exists)
   <div class="inline-error">Session state has not been verified. Stop the listener, then use “Check session status”. If verification fails, see storage/logs/laravel.log. No session files are deleted.</div>
  @elseif($state==='signup')
   <div class="inline-error">This phone number needs a new Telegram account. Complete registration using the official Telegram app first.</div>
  @else
   <div class="helper-note"><b>Before signing in</b><p>Stop the Telegram listener to prevent concurrent session changes. Start it again once login is complete.</p></div>
   <div class="step-current"><span class="step-badge">01 / 03</span><h4>Enter your phone number</h4><p>Use a Telegram user account that can access the source channels. The code will usually arrive in your Telegram app.</p></div>
   <form method="post" action="{{ route('telegram.login.phone') }}">@csrf<label class="field-label" for="tg_phone">Phone number (international format)</label><input class="field" type="tel" id="tg_phone" name="phone" dir="ltr" value="{{ old('phone') }}" maxlength="16" placeholder="+491234567890" pattern="\+[1-9][0-9]{6,14}" required autocomplete="tel"><button class="btn btn-dark wide" type="submit" @disabled(!$credentials_set || $busy)>Send login code <svg class="ico"><use href="#i-telegram"/></svg></button></form>
  @endif
</section>
<section class="surface"><span class="eyebrow">SETUP CHECKLIST</span><h3>Connect your source account</h3>
  <div class="step"><span>01</span><div><b>Sign in</b><small>Phone, verification code and two-step password if enabled</small></div></div>
  <div class="step"><span>02</span><div><b>Add sources</b><small>Define the Telegram channels to monitor</small></div></div>
  <div class="step"><span>03</span><div><b>Start the listener</b><small>Run <code>php artisan rdm:listen</code> once connected</small></div></div>
  <div class="step"><span>04</span><div><b>Review incoming videos</b><small>Only new videos from enabled channels are collected</small></div></div>
  <div class="helper-note"><b>Security &amp; session</b><p>Credentials are sent directly to MadelineProto, not saved in the application database. Telegram session files stay in private storage. Keep the admin panel behind HTTPS.</p></div>
  <a class="text-link" href="{{ route('channels.index') }}">Manage source channels <svg class="ico"><use href="#i-arrow"/></svg></a>
</section></div>
@endsection
