@extends('layouts.employee')
@section('title','Slip Gaji')
@section('breadcrumb')<span class="current">Payroll</span>@endsection
@section('content')
<div class="page-title">Slip Gaji</div>
<div class="page-sub">Riwayat payroll dan slip gaji kamu.</div>

<div class="card">
    <div class="card-header" style="margin-bottom:12px">
        <span class="card-title">Riwayat Payroll</span>
        <form method="GET" style="display:flex;gap:8px">
            <select name="status" class="btn" style="padding:6px 10px;width:auto">
                <option value="">Semua</option>
                @foreach(['draft','approved','paid'] as $s)
                    <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Periode</th><th>Gaji Pokok</th><th>Tunjangan</th><th>Potongan</th><th>Take Home</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse($data['data'] ?? $data as $p)
                    <tr>
                        <td>{{ isset($p['period_start']) ? \Carbon\Carbon::parse($p['period_start'])->isoFormat('MMM Y') : '—' }}</td>
                        <td>Rp {{ number_format($p['base_salary'] ?? 0,0,',','.') }}</td>
                        <td>Rp {{ number_format($p['allowance'] ?? $p['total_allowance'] ?? 0,0,',','.') }}</td>
                        <td>Rp {{ number_format($p['deduction'] ?? $p['total_deduction'] ?? 0,0,',','.') }}</td>
                        <td style="font-weight:600">Rp {{ number_format($p['net_salary'] ?? $p['take_home'] ?? 0,0,',','.') }}</td>
                        <td>
                            @if($p['status']==='paid') <span class="badge badge-success">Dibayar</span>
                            @elseif($p['status']==='approved') <span class="badge badge-info">Approved</span>
                            @else <span class="badge badge-gray">Draft</span>
                            @endif
                        </td>
                        <td><a href="{{ route('employee.payroll.show', $p['id']) }}" class="btn btn-sm">Lihat Slip</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty"><div class="empty-icon">💰</div><div class="empty-text">Belum ada data payroll.</div></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($data['last_page']) && $data['last_page'] > 1)
        <div class="pagination">
            @for($p=1;$p<=$data['last_page'];$p++)
                <a href="{{ request()->fullUrlWithQuery(['page'=>$p]) }}" class="{{ $p==($data['current_page']??1)?'active':'' }}">{{ $p }}</a>
            @endfor
        </div>
    @endif
</div>
@endsection