{{-- resources/views/pages/santri/performance/index.blade.php --}}
@extends('layouts.Santri')
@section('title','Performa Saya')

@push('styles')
@include('pages.santri._shared_css')
<style>
.score-bar-wrap { background:var(--gray-100);border-radius:999px;height:10px;overflow:hidden; }
.score-bar { height:100%;border-radius:999px;transition:width .6s; }
.score-item { display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--gray-100); }
.score-item:last-child { border-bottom:none; }
.score-label { font-size:.85rem;color:var(--gray-700);min-width:130px;font-weight:600; }
.score-val   { font-size:.85rem;font-weight:800;color:var(--gray-900);min-width:36px;text-align:right; }
</style>
@endpush

@section('content')
<div class="container-fluid s-page">
    <div class="s-header">
        <div>
            <h1 class="s-title">⭐ Performa Saya</h1>
            <p class="s-sub">Skor penilaian & evaluasi bulanan</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:8px;">
                <select name="month" class="s-control" style="width:auto;" onchange="this.form.submit()">
                    @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('M') }}</option>
                    @endfor
                </select>
                <select name="year" class="s-control" style="width:auto;" onchange="this.form.submit()">
                    @for($y=now()->year;$y>=now()->year-2;$y--)
                    <option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>
            <a href="{{ route('santri.performance.leaderboard',['month'=>$month,'year'=>$year]) }}" class="s-btn s-btn-gold s-btn-sm">🏆 Leaderboard</a>
        </div>
    </div>

    @if(empty($scores))
    <div class="s-card"><div class="s-empty">
        <div class="s-empty-icon">⭐</div>
        <p class="s-empty-title">Belum ada data performa</p>
        <p class="s-empty-desc">Data performa bulan ini belum tersedia.</p>
    </div></div>
    @else
    <div class="row g-4">
        <div class="col-md-4">
            <div class="s-card" style="text-align:center;">
                @php $total = $scores['total_score'] ?? 0; $max = $scores['max_score'] ?? 100; $pct = $max > 0 ? ($total/$max*100) : 0; @endphp
                <div style="font-size:3rem;font-weight:900;color:{{ $pct>=80?'var(--p)':($pct>=60?'var(--gold)':'var(--red)') }};line-height:1;">{{ number_format($total,1) }}</div>
                <div style="font-size:.85rem;color:var(--gray-500);margin:4px 0 16px;">dari {{ $max }} poin</div>
                <div class="score-bar-wrap">
                    <div class="score-bar" style="width:{{ min($pct,100) }}%;background:{{ $pct>=80?'var(--p)':($pct>=60?'var(--gold)':'var(--red)') }};"></div>
                </div>
                <div style="font-size:.8rem;color:var(--gray-400);margin-top:8px;">{{ number_format($pct,1) }}%</div>
                @if(!empty($scores['rank']))<div style="margin-top:12px;"><span class="s-badge s-badge-gold">🏅 Peringkat #{{ $scores['rank'] }}</span></div>@endif
            </div>
        </div>
        <div class="col-md-8">
            <div class="s-card">
                <p class="s-card-title">Rincian Skor</p>
                @foreach($scores['details'] ?? [] as $d)
                @php $bar = isset($d['score'],$d['max_score']) && $d['max_score']>0 ? ($d['score']/$d['max_score']*100) : 0; @endphp
                <div class="score-item">
                    <span class="score-label">{{ $d['label'] ?? '-' }}</span>
                    <div style="flex:1;" class="score-bar-wrap">
                        <div class="score-bar" style="width:{{ min($bar,100) }}%;background:var(--p);"></div>
                    </div>
                    <span class="score-val">{{ $d['score'] ?? 0 }}/{{ $d['max_score'] ?? 0 }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
