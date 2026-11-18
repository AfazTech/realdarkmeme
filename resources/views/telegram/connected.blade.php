@extends('layouts.app')
@section('title','Telegram Account')
@section('content')
<section class="surface"><h2>Telegram connected</h2><p>{{ trim(($me['first_name']??'').' '.($me['last_name']??'')) }}</p><a class="btn btn-dark" href="{{ route('telegram.index') }}">Return to Telegram account</a></section>
@endsection
