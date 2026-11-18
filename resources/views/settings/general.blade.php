@extends('layouts.app')
@section('title','App Settings')
@section('content')
<div class="page-intro"><div><span class="eyebrow">APPLICATION / CONFIGURATION</span><h1>App Settings <span class="soft-dot">.</span></h1><p>Manage content defaults, delivery connections and administrator security on separate pages.</p></div></div>
<div class="settings-home-grid">
 <a class="settings-home-card" href="{{ route('settings.captions') }}"><span class="eyebrow">CONTENT DEFAULTS</span><h3>Caption Defaults</h3><p>Edit the global hashtags appended to video captions.</p><span class="settings-card-arrow">Edit captions →</span></a>
 <a class="settings-home-card" href="{{ route('senders.index') }}"><span class="eyebrow">DELIVERY</span><h3>Senders</h3><p>Manage multiple publication destinations, bot credentials and activation.</p><span class="settings-card-arrow">Manage Senders →</span></a>
 <a class="settings-home-card" href="{{ route('settings.security') }}"><span class="eyebrow">ADMINISTRATION</span><h3>Admin Security</h3><p>Configure allowed IP addresses and the administrator password.</p><span class="settings-card-arrow">Manage security →</span></a>
 <a class="settings-home-card" href="{{ route('settings.index') }}"><span class="eyebrow">CREATIVE</span><h3>Studio Settings</h3><p>Cover, watermark, video, outro, output and media assets.</p><span class="settings-card-arrow">Open studio settings →</span></a>
</div>
@endsection
