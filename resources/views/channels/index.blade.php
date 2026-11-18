@extends('layouts.app')
@section('title','Source Channels')
@section('content')
<div class="page-intro"><div><span class="eyebrow">TELEGRAM / SOURCES</span><h1>Source Channels <span class="soft-dot">.</span></h1><p>Only new videos from enabled channels enter the queue.</p></div><span class="small-counter">{{ $channels->count() }} channels</span></div>
<div class="two-col"><section class="surface"><div class="box-heading"><div><span class="eyebrow">ACTIVE SOURCES</span><h3>Configured sources</h3></div></div>
@forelse($channels as $channel)<div class="channel-row"><div class="channel-icon">TG</div><form action="{{ route('channels.update',$channel) }}" method="post" class="channel-form">@csrf @method('PATCH')<div class="channel-text"><input class="inline-edit" name="label" value="{{ $channel->label }}" maxlength="120" aria-label="Channel title"><small dir="ltr">{{ $channel->peer_id }} · {{ $channel->videos_count }} videos</small></div><label class="toggle-label"><input type="checkbox" value="1" name="enabled" aria-label="Channel enabled" @checked($channel->enabled)><span></span></label><button class="btn btn-xs btn-subtle" type="submit">Save</button></form></div>
@empty<div class="empty-inline">No channels added yet.</div>@endforelse
</section><section class="surface"><span class="eyebrow">NEW SOURCE</span><h3>Add a source channel</h3><p class="subparagraph">The connected Telegram account must have access to the channel. Use the exact numeric peer ID.</p>
<form method="post" action="{{ route('channels.store') }}">@csrf
<label class="field-label">Display name</label><input class="field" name="label" value="{{ old('label') }}" required maxlength="120" placeholder="e.g. Daily Memes">
<label class="field-label">Numeric channel peer ID</label><input class="field" name="peer_id" value="{{ old('peer_id') }}" dir="ltr" required placeholder="-1001234567890">
<button class="btn btn-dark wide" type="submit">Add channel <svg class="ico"><use href="#i-arrow"/></svg></button></form>
<div class="helper-note"><b>Why peer ID?</b><p>The listener matches the numeric Telegram chat ID. Public usernames are not required, including for private channels.</p></div>
</section></div>
@endsection
