@extends('layouts.app')
@section('title', $title)
@section('content')
<h1 class="text-2xl font-bold tracking-tight">{{ $title }}</h1>
<div class="card mt-6 grid place-items-center gap-2 p-16 text-center">
    <span class="pill pill-ac">{{ $phase }}</span>
    <p class="text-ink2">Dieser Bereich wird in {{ $phase }} umgesetzt.</p>
</div>
@endsection
