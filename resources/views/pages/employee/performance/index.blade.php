@extends('layouts.employee')
@section('title','Skor Performa')
@section('breadcrumb')<span class="current">Performa</span>@endsection
@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <div class="page-title">Skor Performa</div>
    <a href="{{ route('employee.performance.leaderboard', ['month'=>$month,'year'=>$year]) }}" class="btn btn-sm">🏆 Leaderboard</a>
</div>
<div class="page-sub">Pantau perkembangan skor performa kamu setiap bulan.</div>

<div class="card">
    <div class="card-header" style="margin-bottom:12px">
        <span class="card-title">Riwayat Skor</span>
        <form method="GET" style="display:flex;gap:8px">
            <select name="month" class="btn" style="padding:6px 10px;width:auto">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ \Carbon\Carbon::create(null,$m)->isoFormat('MMMM') }}</option>
                @endforeach
            </select>
            <select name="year" class="btn" style="padding:6px 10px;width:auto">
                @foreach([now()->year, now()->year-1] as $y)
                    <option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Periode</th><th>Skor Kehadiran</th><th>Skor Laporan</th><th>Skor Lainnya</th><th>Skor Final</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($scores['data'] ?? $scores as $s)
                    <tr>
                        <td>{{ \Carbon\Carbon::create($s['year'],$s['month'])->isoFormat('MMMM Y') }}</td>
                        <td>{{ $s['attendance_score'] ?? '—' }}</td>
                        <td>{{ $s['report_score'] ?? '—' }}</td>
                        <td>{{ $s['other_score'] ?? '—' }}</td>
                        <td style="font-weight:700;font-size:15px;color:var(--c-primary)">{{ $s['final_score'] ?? '—' }}</td>
                        <td><a href="{{ route('employee.performance.show', $s['id']) }}" class="btn btn-sm">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty"><div class="empty-icon">⭐</div><div class="empty-text">Belum ada data skor performa.</div></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection