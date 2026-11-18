@extends('layouts.app')
@section('title','Admin Security')
@section('content')
<div class="page-intro"><div><span class="eyebrow">APPLICATION / ACCESS</span><h1>Admin Security <span class="soft-dot">.</span></h1><p>Restrict administrative access by IP and manage your login password.</p></div></div>
<div class="settings-tabs"><a href="#ip-allowlist">IP allowlist</a><a href="#account">Password</a><a href="{{ route('settings.general') }}">App Settings</a></div>
<section class="surface settings-block" id="ip-allowlist">
<div class="box-heading"><div><span class="eyebrow">NETWORK SECURITY</span><h3>Allowed admin IP addresses</h3></div><span class="section-tag">{{ $enabled ? 'ENABLED' : 'DISABLED' }}</span></div>
<p class="subparagraph">When enabled, only listed addresses can access the sign-in page and administration routes. Supports IPv4, IPv6 and CIDR ranges, one entry per line. No untrusted X-Forwarded-For values are used.</p>
<div class="ip-current"><span>Detected current IP</span><code>{{ $currentIp }}</code></div>
<form method="post" action="{{ route('settings.security.allowlist') }}">@csrf
 <div class="setting-fields" style="display:block">
  <label class="field-label" for="admin-ips">Allowed IP addresses and CIDR ranges</label>
  <textarea name="ips" id="admin-ips" dir="ltr" class="field textarea ip-editor" rows="6" maxlength="6000" spellcheck="false" placeholder="192.0.2.10&#10;2001:db8::/48">{{ old('ips',$allowlist !== '' ? $allowlist : $currentIp) }}</textarea>
  <small class="input-hint">Up to 50 entries. Your current IP must be included before enabling. Broad /0 ranges are rejected. Proxy setups require trusted proxy configuration before using this control.</small>
 </div>
 <label class="ip-enable"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$enabled))><span>Enable IP access restriction</span></label>
 <label class="field-label" for="security-password">Confirm with your current admin password</label>
 <input class="field" type="password" name="current_password" id="security-password" autocomplete="current-password" required>
 <div class="form-footer"><span>If your network changes, use <code>php artisan rdm:ip-unlock</code> from the server console.</span><button class="btn btn-dark" type="submit">Save IP restrictions</button></div>
</form>
</section>
<section class="surface settings-block" id="account"><span class="eyebrow">ADMIN SECURITY</span><h3>Change admin password</h3><p class="subparagraph">The password cannot be empty. A long, unique password is recommended.</p>
<form method="post" action="{{ route('settings.password') }}">@csrf<div class="field-grid"><div><label class="field-label">Current password</label><input class="field" type="password" name="current_password" required autocomplete="current-password"></div><div><label class="field-label">New password</label><input class="field" type="password" name="password" required autocomplete="new-password"></div><div><label class="field-label">Confirm new password</label><input class="field" type="password" name="password_confirmation" required autocomplete="new-password"></div></div><div class="form-footer"><span>Your current session remains active after this change.</span><button type="submit" class="btn btn-dark">Change password</button></div></form>
</section>
@endsection
