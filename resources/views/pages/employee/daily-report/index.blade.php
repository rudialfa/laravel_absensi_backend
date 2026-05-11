@extends('layouts.employee')

@section('title', 'Laporan Harian')
@section('breadcrumb')<span class="current">Laporan Harian</span>@endsection

@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <div class="page-title">Laporan Harian</div>
    <a href="{{ route('employee.daily-report.today') }}" class="btn btn-primary">+ Isi Laporan Hari Ini</a>
</div>
<div class="page-sub">Rekap target dan pencapaian harian kamu.</div>

{{-- ── Ringkasan ──────────────────────────────────────────────────────── --}}
<div class="metrics">
    <div class="metric">
        <div class="metric-label">Total Submit</div>
        <div class="metric-val primary">{{ $summary['total_days'] ?? 0 }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Tercapai</div>
        <div class="metric-val success">{{ $summary['total_achieved'] ?? 0 }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Tidak Tercapai</div>
        <div class="metric-val danger">{{ $summary['total_not_achieved'] ?? 0 }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Pending Sore</div>
        <div class="metric-val warning">{{ $summary['total_pending'] ?? 0 }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Achievement Rate</div>
        <div class="metric-val info">{{ $summary['achievement_rate'] ?? 0 }}%</div>
    </div>
</div>

{{-- ── Filter & List ───────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Riwayat Laporan — {{ \Carbon\Carbon::create($year, $month)->isoFormat('MMMM Y') }}</span>
        <form method="GET" style="display:flex;gap:8px">
            <select name="month" class="btn" style="padding:6px 10px;width:auto">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m)->isoFormat('MMMM') }}</option>
                @endforeach
            </select>
            <select name="year" class="btn" style="padding:6px 10px;width:auto">
                @foreach([now()->year, now()->year - 1] as $y)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
            <a href="{{ route('employee.daily-report.export', request()->only('month','year')) }}" class="btn btn-sm">⬇ Export PDF</a>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Target Pagi</th>
                    <th>Pencapaian Sore</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports['data'] ?? $reports as $r)
                    <tr>
                        <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($r['date'])->isoFormat('ddd, D MMM') }}</td>
                        <td style="max-width:220px">
                            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r['target'] }}</div>
                        </td>
                        <td style="max-width:220px">
                            @if($r['achievement'])
                                <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r['achievement'] }}</div>
                            @else
                                <span style="color:var(--c-hint);font-size:12px">Belum diisi</span>
                            @endif
                        </td>
                        <td>
                            @if($r['achievement'] === null)
                                <span class="badge badge-warning">Pending</span>
                            @elseif($r['is_achieved'])
                                <span class="badge badge-success">Tercapai</span>
                            @else
                                <span class="badge badge-danger">Tidak Tercapai</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('employee.daily-report.show', $r['id']) }}" class="btn btn-sm">Lihat</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">
                        <div class="empty"><div class="empty-icon">📋</div><div class="empty-text">Belum ada laporan untuk bulan ini.</div></div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection