@extends('layouts.play')

@section('title', 'Gatcha '.$theme['name'])
@section('theme-color', $theme['st2'])
@section('body-class', 'is-stage')

@php
    $used = $prizes->filter(fn ($p) => $p->taken || ($p->opened && ! $p->picked))->pluck('slot')->all();
    $gift = '<span class="gift-shadow"></span><span class="gift-body"><span class="gift-float">'
        .'<span class="gift-box"></span><span class="gift-ribbon"></span><span class="gift-inner"></span>'
        .'<span class="gift-lid"><span class="gift-lid-top"></span><span class="gift-lid-front"></span><span class="gift-lid-ribbon"></span>'
        .'<span class="gift-bow l"></span><span class="gift-bow r"></span><span class="gift-knot"></span></span>'
        .'</span></span>';
    $payload = [
        'token' => $session->session_token,
        'csrf' => csrf_token(),
        'brand' => $theme['name'],
        'chanceTotal' => $session->chance_total,
        'chanceUsed' => $session->chance_used,
        'pendingSlots' => $pendingSlots,
        'pendingRevealed' => $pendingRevealed,
        'taken' => $taken,
        'formUrl' => route('play.form'),
        'homeUrl' => route('home'),
        'rewardsUrl' => route('rewards'),
        'rewardUrl' => url('/hadiah').'/',
    ];
@endphp

@section('content')
<section class="stage" id="stage" aria-live="polite">
    <div class="stage-rays" aria-hidden="true"></div>
    <div class="stage-spot" aria-hidden="true"></div>
    <div class="stage-floor" aria-hidden="true"></div>
    <div class="stage-dim" id="dim" aria-hidden="true"></div>

    <header class="stage-head">
        <a class="icon-btn" href="{{ route('home') }}" aria-label="Kembali ke Beranda">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>
        </a>
        <div class="stage-brand" aria-hidden="true">{{ $theme['initial'] }}</div>
        <div class="stage-title">Gatcha {{ $theme['name'] }}</div>
        <button type="button" class="icon-btn" id="sound" aria-pressed="false" aria-label="Suara dan musik mati. Ketuk untuk menyalakan">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 9h4l5-4v14l-5-4H4z" fill="currentColor" stroke="none"/>
                <path class="snd-on" d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12" hidden/>
                <path class="snd-off" d="M17 9l5 6M22 9l-5 6"/>
            </svg>
        </button>
        <div class="chance-pill" aria-label="Sisa kesempatan">
            @for ($i = 0; $i < $session->chance_total; $i++)
                <span class="coin" data-coin="{{ $i }}"></span>
            @endfor
            <b id="chanceText"></b>
        </div>
    </header>

    {{-- Pilih 3 dari 24 (2b) --}}
    <div id="pickView" hidden>
        <div class="stage-intro">
            <h1 id="pickTitle">Pilih 3 kado</h1>
            <p id="pickLead">Semua kado berisi hadiah. Isinya diacak server saat dibuka.</p>
        </div>
    </div>
    <div class="board" id="board" hidden>
        <div class="board-grid" id="grid">
            @foreach ($prizes as $prize)
                @php $isUsed = in_array($prize->slot, $used, true); @endphp
                <button type="button" class="cell {{ $isUsed ? 'is-used' : '' }}" data-slot="{{ $prize->slot }}"
                        aria-label="Kado {{ str_pad($prize->slot, 2, '0', STR_PAD_LEFT) }}{{ $isUsed ? ', sudah dibuka' : '' }}"
                        aria-pressed="false" @disabled($isUsed)>
                    <span class="gift {{ $isUsed ? 'is-open' : '' }}" style="--float-delay: {{ number_format(-fmod($prize->slot * 0.37, 3), 2) }}s">{!! $gift !!}</span>
                    <span class="num">{{ str_pad($prize->slot, 2, '0', STR_PAD_LEFT) }}</span>
                </button>
            @endforeach
        </div>
    </div>
    <div class="tray" id="pickTray" hidden>
        <div class="tray-row">
            <span class="label" id="pickLabel">Pilih 3 lagi</span>
            <span class="tray-slot" data-tray="0"></span>
            <span class="tray-slot" data-tray="1"></span>
            <span class="tray-slot" data-tray="2"></span>
        </div>
        <button type="button" class="btn btn-gold" id="openBtn" disabled>Buka 3 kado</button>
    </div>

    {{-- Buka 3 kado, ambil 1 (2c) --}}
    <div id="revealView" hidden>
        <div class="stage-intro">
            <h1 id="revealTitle">Membuka kado…</h1>
            <p>Kamu hanya bisa mengambil 1. Dua lainnya hangus.</p>
        </div>
    </div>
    <div class="reveal" id="reveal" hidden></div>
    <div class="tray" id="revealTray" hidden>
        <div class="tray-note">Pilihan tidak bisa diubah setelah diambil.</div>
        <button type="button" class="btn btn-gold" id="takeBtn" disabled>Tunggu kado terbuka</button>
    </div>

    {{-- Hasil ronde --}}
    <div class="result" id="resultView" hidden></div>
    <div class="tray" id="resultTray" hidden>
        <button type="button" class="btn btn-gold" id="nextBtn">Lanjut</button>
        <a class="btn-ghost" href="{{ route('home') }}" id="laterLink">Nanti saja, kesempatan tersimpan</a>
    </div>

    <div class="fx" id="fx" aria-hidden="true"></div>
    <div class="stage-flash" id="flash" aria-hidden="true"></div>
</section>

<template id="giftTpl">{!! $gift !!}</template>

<script type="application/json" id="gatchaData">@json($payload)</script>
@endsection

@push('scripts')
<script src="{{ asset('js/gatcha.js') }}?v={{ filemtime(public_path('js/gatcha.js')) }}"></script>
@endpush
