<!doctype html>
<html lang="en" dir="ltr">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="theme-color" content="#161c22"><title>@yield('title','Dashboard') | RealDarkMeme</title>
 <link rel="stylesheet" href="{{ asset('assets/studio.css').'?v=2.5' }}">
</head>
<body class="studio">
<svg class="sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
 <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
 <symbol id="i-play" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="m10 9 5 3-5 3z"/></symbol>
 <symbol id="i-telegram" viewBox="0 0 24 24"><path d="m21 3-3.7 18-5.7-4-3.1 2 .4-4.2L3 12z"/><path d="M8.5 14.8 21 3"/></symbol>
 <symbol id="i-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M10 2h4l.5 2.3 2 .9 2.1-1.1 2.9 2.9-1.2 2.2.8 2.1L23 12v2l-2.3.5-.9 2 1.1 2.1-2.9 2.9-2.2-1.2-2.1.8L12 23h-2l-.5-2.3-2-.9-2.1 1.1-2.9-2.9-2.2 1.2-2.1-.8L1 12v-2l2.3-.5.9-2-1.1-2.1L6 2.5l2.2 1.2 2.1-.8z" transform="translate(0 -1) scale(.96)"/></symbol>
 <symbol id="i-layers" viewBox="0 0 24 24"><path d="m12 2 9 5-9 5-9-5zM3 12l9 5 9-5M3 17l9 5 9-5"/></symbol>
 <symbol id="i-arrow" viewBox="0 0 24 24"><path d="m4 12h16m-7-7 7 7-7 7"/></symbol>
 <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/></symbol>
 <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 17V4m-5 5 5-5 5 5M4 16v4h16v-4"/></symbol>
 <symbol id="i-check" viewBox="0 0 24 24"><path d="m4 12 5 5L20 6"/></symbol>
 <symbol id="i-logout" viewBox="0 0 24 24"><path d="M11 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6M15 7l5 5-5 5m-8-5h13"/></symbol>
 <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
 <symbol id="i-spark" viewBox="0 0 24 24"><path d="m12 2 2.3 7.7L22 12l-7.7 2.3L12 22l-2.3-7.7L2 12l7.7-2.3z"/></symbol>
</svg>
<div class="shell" id="appShell">
 <aside class="sidebar" id="sidebar">
  <div class="brand"><span class="brand-mark"><span>R</span><i></i></span><span class="brand-copy"><b>REALDARKMEME</b><small>CONTENT STUDIO <span class="ver">/ 2.3</span></small></span></div>
  <div class="sidebar-sep"></div>
  <div class="nav-heading">WORKSPACE</div>
  <nav class="side-nav" aria-label="Main navigation">
   <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard')?'current':'' }}"><svg class="ico"><use href="#i-grid"/></svg> Dashboard</a>
   <a href="{{ route('videos.index') }}" class="nav-link {{ request()->routeIs('videos.*')?'current':'' }}"><svg class="ico"><use href="#i-play"/></svg> Video Library</a>
   <a href="{{ route('channels.index') }}" class="nav-link {{ request()->routeIs('channels.*')?'current':'' }}"><svg class="ico"><use href="#i-layers"/></svg> Source Channels</a>
  </nav>
  <div class="nav-heading second">CONNECTIONS &amp; SETTINGS</div>
  <nav class="side-nav">
   <a href="{{ route('telegram.index') }}" class="nav-link {{ request()->routeIs('telegram.*')?'current':'' }}"><svg class="ico"><use href="#i-telegram"/></svg> Telegram Account</a>
   <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.index')?'current':'' }}"><svg class="ico"><use href="#i-spark"/></svg> Studio Settings</a>
   <a href="{{ route('settings.general') }}" class="nav-link {{ request()->routeIs('settings.general')?'current':'' }}"><svg class="ico"><use href="#i-settings"/></svg> App Settings</a>
  </nav>
  <div class="sidebar-bottom">
   <div class="sidebar-note"><span class="alive-dot"></span><b>Studio engine</b><small>Laravel / FFmpeg / OCR</small></div>
   <div class="account-info"><div class="avatar">{{ strtoupper(mb_substr(auth()->user()->username,0,1)) }}</div><div><strong>{{ auth()->user()->username }}</strong><small>Administrator</small></div>
    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="icon-button logout" title="Sign out" aria-label="Sign out"><svg class="ico"><use href="#i-logout"/></svg></button></form>
   </div>
  </div>
 </aside>
 <div class="workarea">
  <header class="topbar"><div class="topbar-start"><button type="button" id="mobileMenu" class="icon-button hamburger" aria-label="Open navigation"><svg class="ico"><use href="#i-menu"/></svg></button><div class="breadcrumb">Studio <span>/</span> <strong>@yield('title','Dashboard')</strong></div></div><div class="top-actions"><span class="top-date">{{ now()->format('M d, Y') }}</span><span class="top-chip"><i></i> REALDARKMEME</span></div></header>
  <main class="content">
   @if(session('ok'))<div class="notice success"><svg class="ico"><use href="#i-check"/></svg>{{ session('ok') }}</div>@endif
   @if($errors->any())<div class="notice error"><strong>Could not complete the request:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
   @yield('content')
  </main>
  <footer class="footer"><span>RealDarkMeme Content Studio</span><span>Laravel / Private workspace</span></footer>
 </div>
</div>
<div class="backdrop" id="mobileBackdrop"></div>
<script src="{{ asset('assets/studio.js') }}" defer></script>
</body></html>
