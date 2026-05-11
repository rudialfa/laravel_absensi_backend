@extends('layouts.employee')

@section('title', 'Laporan Bulanan')
@section('breadcrumb')<span class="current">Laporan Bulanan</span>@endsection

@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <div class="page-title">Laporan Bulanan</div>
    <a href="{{ route('employee.monthly-report.create') }}" class="btn btn-primary">+ Buat Laporan</a>
</div>
<div class="page-sub">Rekap target, pencapaian, masalah & solusi setiap bulan.</div>

{{-- Ringkasan --}}
<div class="metrics">
    <div class="metric"><div class="metric-label">Total</div><div class="metric-val primary">{{ $summary['total'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Draft</div><div class="metric-val warning">{{ $summary['draft'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Submitted</div><div class="metric-val info">{{ $summary['submitted'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Approved</div><div class="metric-val success">{{ $summary['approved'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Rejected</div><div class="metric-val danger">{{ $summary['rejected'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Rata-rata Skor</div><div class="metric-val primary">{{ $summary['avg_score'] ?? 0 }}</div></div>
</div>

{{-- Filter + Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Riwayat — Tahun {{ $year }}</span>
        <form method="GET" style="display:flex;gap:8px">
            <select name="year" class="btn" style="padding:6px 10px;width:auto">
                @foreach([now()->year, now()->year-1, now()->year-2] as $y)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected':'' }}>{{ $y }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Periode</th><th>Target</th><th>Status</th><th>Skor</th><th>Direview oleh</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @php $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des']; @endphp
                @forelse($reports['data'] ?? $reports as $r)
                    <tr>
                        <td style="font-weight:500;white-space:nowrap">{{ $bulan[$r['month']] }} {{ $r['year'] }}</td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r['target'] }}</td>
                        <td>
                            @if($r['status']==='draft') <span class="badge badge-gray">Draft</span>
                            @elseif($r['status']==='submitted') <span class="badge badge-info">Submitted</span>
                            @elseif($r['status']==='approved') <span class="badge badge-success">Approved</span>
                            @elseif($r['status']==='rejected') <span class="badge badge-danger">Rejected</span>
                            @endif
                        </td>
                        <td>{{ $r['score'] ?? '—' }}</td>
                        <td>{{ $r['approver']['name'] ?? '—' }}</td>
                        <td style="white-space:nowrap;display:flex;gap:6px">
                            <a href="{{ route('employee.monthly-report.show', $r['id']) }}" class="btn btn-sm">Lihat</a>
                            @if(in_array($r['status'], ['draft','rejected']))
                                <a href="{{ route('employee.monthly-report.edit', $r['id']) }}" class="btn btn-sm">Edit</a>
                            @endif
                            @if($r['status']==='draft')
                                <form method="POST" action="{{ route('employee.monthly-report.submit', $r['id']) }}" onsubmit="return confirm('Submit laporan ini ke HR?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">Submit</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <div class="empty"><div class="empty-icon">📊</div><div class="empty-text">Belum ada laporan bulanan.</div></div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection