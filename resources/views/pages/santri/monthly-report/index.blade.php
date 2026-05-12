{{-- resources/views/pages/santri/monthly-report/index.blade.php --}}
@extends('layouts.Santri')
@section('title','Laporan Bulanan')

@push('styles')
@include('pages.santri._shared_css')
@endpush

@section('content')
<div class="container-fluid s-page">
    @if(session('success'))<div class="s-alert s-alert-success"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="s-alert s-alert-error"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ session('error') }}</div>@endif

    <div class="s-header">
        <div>
            <h1 class="s-title">📊 Laporan Bulanan</h1>
            <p class="s-sub">Rekap capaian & evaluasi per bulan</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:8px;align-items:center;">
                <select name="year" class="s-control" style="width:auto;" onchange="this.form.submit()">
                    @for($y=now()->year;$y>=now()->year-3;$y--)
                    <option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>
            <a href="{{ route('santri.monthly-report.create') }}" class="s-btn s-btn-primary s-btn-sm">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Buat Laporan
            </a>
        </div>
    </div>

    {{-- Summary --}}
    @if(!empty($summary))
    <div class="s-stats">
        <div class="s-stat-card"><div class="s-stat-val">{{ $summary['total'] ?? 0 }}</div><div class="s-stat-label">Total Laporan</div></div>
        <div class="s-stat-card"><div class="s-stat-val" style="color:var(--gold)">{{ $summary['draft'] ?? 0 }}</div><div class="s-stat-label">Draft</div></div>
        <div class="s-stat-card"><div class="s-stat-val" style="color:var(--blue)">{{ $summary['submitted'] ?? 0 }}</div><div class="s-stat-label">Tersubmit</div></div>
        <div class="s-stat-card"><div class="s-stat-val" style="color:var(--p)">{{ $summary['reviewed'] ?? 0 }}</div><div class="s-stat-label">Direview</div></div>
    </div>
    @endif

    {{-- Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
        @forelse($reports as $r)
        @php
            $st = $r['status'] ?? 'draft';
            $bdg = match($st){ 'reviewed'=>['s-badge-green','Direview'],'submitted'=>['s-badge-blue','Tersubmit'],default=>['s-badge-gold','Draft'] };
            $monthName = isset($r['month']) ? \Carbon\Carbon::create()->month($r['month'])->translatedFormat('F') : '-';
        @endphp
        <div class="s-card" style="display:flex;flex-direction:column;gap:14px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <p style="font-size:1.05rem;font-weight:700;color:var(--gray-900);margin:0 0 3px;">{{ $monthName }} {{ $r['year'] ?? $year }}</p>
                    <span class="s-badge {{ $bdg[0] }}">{{ $bdg[1] }}</span>
                </div>
            </div>
            @if(!empty($r['target']))<p style="font-size:.83rem;color:var(--gray-600);margin:0;">{{ \Str::limit($r['target'],80) }}</p>@endif
            <div style="display:flex;gap:8px;margin-top:auto;flex-wrap:wrap;">
                <a href="{{ route('pages.santri.monthly-report.show', $r['id']) }}" class="s-btn s-btn-outline s-btn-sm">Detail</a>
                @if($st === 'draft')
                <a href="{{ route('pages.santri.monthly-report.edit', $r['id']) }}" class="s-btn s-btn-primary s-btn-sm">Edit</a>
                <form method="POST" action="{{ route('pages.santri.monthly-report.submit', $r['id']) }}" style="display:inline;">
                    @csrf @method('PATCH')
                    <button class="s-btn s-btn-gold s-btn-sm" type="submit">Submit</button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <div class="s-card" style="grid-column:1/-1;">
            <div class="s-empty">
                <div class="s-empty-icon"><svg width="24" height="24" fill="none" stroke="#adb5bd" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <p class="s-empty-title">Belum ada laporan</p>
                <p class="s-empty-desc">Belum ada laporan bulanan di tahun {{ $year }}.</p>
            </div>
        </div>
        @endforelse
    </div>
</div>
@endsection
