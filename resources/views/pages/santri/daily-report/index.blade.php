{{-- resources/views/pages/santri/daily-report/index.blade.php --}}
@extends('layouts.Santri')
@section('title','Riwayat Laporan Harian')

@push('styles')
@include('pages.santri._shared_css')
@endpush

@section('content')
<div class="container-fluid s-page">

    <div class="s-header">
        <div>
            <h1 class="s-title">📅 Riwayat Laporan Harian</h1>
            <p class="s-sub">Rekap laporan harian per bulan</p>
        </div>
        <a href="{{ route('santri.daily-report.today') }}" class="s-btn s-btn-primary s-btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Hari Ini
        </a>
    </div>

    {{-- Filter --}}
    <div class="s-filter">
        <form method="GET" class="s-filter-grid">
            <div>
                <label class="s-label">Bulan</label>
                <select name="month" class="s-control" onchange="this.form.submit()">
                    @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="s-label">Tahun</label>
                <select name="year" class="s-control" onchange="this.form.submit()">
                    @for($y=now()->year;$y>=now()->year-2;$y--)
                    <option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </form>
    </div>

    {{-- Summary --}}
    @if(!empty($summary))
    <div class="s-stats">
        <div class="s-stat-card"><div class="s-stat-val" style="color:var(--p)">{{ $summary['total'] ?? 0 }}</div><div class="s-stat-label">Total Laporan</div></div>
        <div class="s-stat-card"><div class="s-stat-val" style="color:var(--gold)">{{ $summary['target_submitted'] ?? 0 }}</div><div class="s-stat-label">Target Diisi</div></div>
        <div class="s-stat-card"><div class="s-stat-val" style="color:var(--blue)">{{ $summary['achievement_submitted'] ?? 0 }}</div><div class="s-stat-label">Pencapaian Diisi</div></div>
        <div class="s-stat-card"><div class="s-stat-val" style="color:var(--p)">{{ $summary['achieved'] ?? 0 }}</div><div class="s-stat-label">Tercapai</div></div>
        <div class="s-stat-card"><div class="s-stat-val" style="color:var(--red)">{{ $summary['not_achieved'] ?? 0 }}</div><div class="s-stat-label">Belum Tercapai</div></div>
    </div>
    @endif

    {{-- Table --}}
    <div class="s-card">
        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Target Pagi</th>
                        <th>Pencapaian</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $r)
                    @php
                        $achieved = $r['is_achieved'] ?? null;
                        $badge = match(true) {
                            $achieved === true  || $achieved == 1 => ['s-badge-green','✅ Tercapai'],
                            $achieved === false || $achieved == 0 => ['s-badge-red','❌ Belum'],
                            !empty($r['target']) => ['s-badge-gold','⏳ Menunggu'],
                            default => ['s-badge-gray','—'],
                        };
                    @endphp
                    <tr>
                        <td style="white-space:nowrap;font-weight:600;">{{ isset($r['date']) ? \Carbon\Carbon::parse($r['date'])->translatedFormat('d M Y') : '-' }}</td>
                        <td style="max-width:220px;">
                            @if(!empty($r['target']))
                                <span style="font-size:.83rem;color:var(--gray-700);">{{ \Str::limit($r['target'],60) }}</span>
                            @else
                                <span style="color:var(--gray-400);font-size:.8rem;">Belum diisi</span>
                            @endif
                        </td>
                        <td style="max-width:220px;">
                            @if(!empty($r['achievement']))
                                <span style="font-size:.83rem;color:var(--gray-700);">{{ \Str::limit($r['achievement'],60) }}</span>
                            @else
                                <span style="color:var(--gray-400);font-size:.8rem;">Belum diisi</span>
                            @endif
                        </td>
                        <td><span class="s-badge {{ $badge[0] }}">{{ $badge[1] }}</span></td>
                        <td>
                            <a href="{{ route('pages.santri.daily-report.show', $r['id']) }}" class="s-btn s-btn-outline s-btn-sm">Detail</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5"><div class="s-empty">
                        <div class="s-empty-icon"><svg width="24" height="24" fill="none" stroke="#adb5bd" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                        <p class="s-empty-title">Belum ada laporan</p>
                        <p class="s-empty-desc">Tidak ada laporan harian bulan ini.</p>
                    </div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
