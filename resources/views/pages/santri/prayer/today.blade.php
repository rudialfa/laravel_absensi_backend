{{-- resources/views/pages/santri/prayer/today.blade.php --}}
@extends('layouts.Santri')
@section('title','Jadwal Sholat')

@push('styles')
@include('pages.santri._shared_css')
<style>
.prayer-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:22px; }
.prayer-card { background:#fff;border:1.5px solid var(--gray-200);border-radius:var(--radius);padding:18px 14px;text-align:center;box-shadow:var(--shadow); }
.prayer-card.active { background:linear-gradient(135deg,var(--p),var(--p-dark));border-color:transparent;color:#fff;box-shadow:0 8px 24px rgba(30,107,79,.3); }
.prayer-name { font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;opacity:.75;margin:0 0 6px; }
.prayer-time { font-size:1.3rem;font-weight:800;margin:0;line-height:1; }
.next-card   { background:var(--p-light);border:2px solid #b2d8c3;border-radius:var(--radius);padding:18px 20px;margin-bottom:22px;display:flex;align-items:center;gap:16px; }
</style>
@endpush

@section('content')
<div class="container-fluid s-page">
    <div class="s-header">
        <div>
            <h1 class="s-title">🕌 Jadwal Sholat</h1>
            <p class="s-sub">{{ now()->translatedFormat('l, d F Y') }} — {{ $prayer['location'] ?? 'Lokasi pesantren' }}</p>
        </div>
        <a href="{{ route('santri.prayer.monthly') }}" class="s-btn s-btn-outline s-btn-sm">📅 Bulanan</a>
    </div>

    {{-- Next prayer --}}
    @if(!empty($next))
    <div class="next-card">
        <div style="font-size:2rem;">⏰</div>
        <div>
            <p style="font-size:.78rem;color:var(--p);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin:0 0 2px;">Waktu Sholat Berikutnya</p>
            <p style="font-size:1.1rem;font-weight:800;color:var(--p-dark);margin:0;">{{ $next['name'] ?? '-' }} — {{ $next['time'] ?? '-' }}</p>
            @if(!empty($next['remaining']))<p style="font-size:.8rem;color:var(--p);margin:2px 0 0;">⏳ {{ $next['remaining'] }}</p>@endif
        </div>
    </div>
    @endif

    {{-- Prayer times --}}
    <div class="prayer-grid">
        @foreach(['subuh','dzuhur','ashar','maghrib','isya'] as $p)
        @php
            $time = $prayer[$p] ?? '-';
            $isActive = ($next['name'] ?? '') === ucfirst($p);
        @endphp
        <div class="prayer-card {{ $isActive ? 'active' : '' }}">
            <p class="prayer-name">{{ ucfirst($p) }}</p>
            <p class="prayer-time">{{ $time }}</p>
        </div>
        @endforeach
    </div>

    {{-- Imsak / Syuruq if available --}}
    @if(!empty($prayer['imsak']) || !empty($prayer['syuruq']))
    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:22px;">
        @if(!empty($prayer['imsak']))
        <div class="s-card" style="flex:1;min-width:140px;text-align:center;">
            <p style="font-size:.75rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;letter-spacing:.05em;margin:0 0 6px;">Imsak</p>
            <p style="font-size:1.2rem;font-weight:800;color:var(--gray-800);margin:0;">{{ $prayer['imsak'] }}</p>
        </div>
        @endif
        @if(!empty($prayer['syuruq']))
        <div class="s-card" style="flex:1;min-width:140px;text-align:center;">
            <p style="font-size:.75rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;letter-spacing:.05em;margin:0 0 6px;">Syuruq</p>
            <p style="font-size:1.2rem;font-weight:800;color:var(--gray-800);margin:0;">{{ $prayer['syuruq'] }}</p>
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
