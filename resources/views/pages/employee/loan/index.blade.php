@extends('layouts.employee')
@section('title','Pinjaman')
@section('breadcrumb')<span class="current">Pinjaman</span>@endsection
@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <div class="page-title">Pinjaman</div>
    <a href="{{ route('employee.loan.create') }}" class="btn btn-primary">+ Ajukan Pinjaman</a>
</div>
<div class="page-sub">Kelola pinjaman dan histori cicilan kamu.</div>

<div class="card">
    <div class="card-header" style="margin-bottom:12px">
        <span class="card-title">Riwayat Pinjaman</span>
        <form method="GET" style="display:flex;gap:8px">
            <select name="status" class="btn" style="padding:6px 10px;width:auto">
                <option value="">Semua</option>
                @foreach(['pending','active','settled','canceled'] as $s)
                    <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Jumlah</th><th>Cicilan/Bulan</th><th>Sisa</th><th>Progress</th><th>Kategori</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse($data as $l)
                    <tr>
                        <td>Rp {{ number_format($l['amount'],0,',','.') }}</td>
                        <td>Rp {{ number_format($l['monthly_installment'],0,',','.') }}</td>
                        <td>Rp {{ number_format($l['balance'],0,',','.') }}</td>
                        <td style="min-width:100px">
                            <div class="progress"><div class="progress-fill" style="width:{{ $l['progress_percent'] }}%"></div></div>
                            <div style="font-size:11px;color:var(--c-muted);text-align:right">{{ $l['progress_percent'] }}%</div>
                        </td>
                        <td style="text-transform:capitalize">{{ str_replace('_',' ',$l['purpose_category']) }}</td>
                        <td>
                            @if($l['status']==='pending') <span class="badge badge-warning">Pending</span>
                            @elseif($l['status']==='active') <span class="badge badge-info">Aktif</span>
                            @elseif($l['status']==='settled') <span class="badge badge-success">Lunas</span>
                            @elseif($l['status']==='canceled') <span class="badge badge-gray">Canceled</span>
                            @endif
                        </td>
                        <td style="display:flex;gap:6px">
                            <a href="{{ route('employee.loan.show', $l['id']) }}" class="btn btn-sm">Detail</a>
                            @if($l['status']==='pending')
                                <form method="POST" action="{{ route('employee.loan.cancel', $l['id']) }}" onsubmit="return confirm('Batalkan pengajuan pinjaman?')">
                                    @csrf <button class="btn btn-sm btn-danger">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty"><div class="empty-icon">💳</div><div class="empty-text">Belum ada pinjaman.</div></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection