@extends('layouts.employee')
@section('title','Pengajuan Lembur')
@section('breadcrumb')<span class="current">Lembur</span>@endsection
@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <div class="page-title">Pengajuan Lembur</div>
    <a href="{{ route('employee.overtime.create') }}" class="btn btn-primary">+ Ajukan Lembur</a>
</div>
<div class="page-sub">Rekap pengajuan lembur kamu.</div>

<div class="card">
    <div class="card-header" style="margin-bottom:12px">
        <span class="card-title">Riwayat Pengajuan</span>
        <form method="GET" style="display:flex;gap:8px">
            <select name="status" class="btn" style="padding:6px 10px;width:auto">
                <option value="">Semua Status</option>
                @foreach(['pending','approved','rejected','canceled'] as $s)
                    <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Tanggal</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Status</th><th>Disetujui</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse($data['data'] ?? $data as $o)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($o['date'])->isoFormat('D MMM Y') }}</td>
                        <td style="font-family:'DM Mono',monospace">{{ substr($o['start_time'],0,5) }}</td>
                        <td style="font-family:'DM Mono',monospace">{{ substr($o['end_time'],0,5) }}</td>
                        <td>{{ $o['minutes'] }} mnt</td>
                        <td>
                            @if($o['status']==='pending') <span class="badge badge-warning">Pending</span>
                            @elseif($o['status']==='approved') <span class="badge badge-success">Approved</span>
                            @elseif($o['status']==='rejected') <span class="badge badge-danger">Rejected</span>
                            @elseif($o['status']==='canceled') <span class="badge badge-gray">Canceled</span>
                            @endif
                        </td>
                        <td>{{ $o['approver']['name'] ?? '—' }}</td>
                        <td style="display:flex;gap:6px">
                            <a href="{{ route('employee.overtime.show', $o['id']) }}" class="btn btn-sm">Lihat</a>
                            @if($o['status']==='pending')
                                <form method="POST" action="{{ route('employee.overtime.cancel', $o['id']) }}" onsubmit="return confirm('Batalkan pengajuan lembur?')">
                                    @csrf <button class="btn btn-sm btn-danger">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty"><div class="empty-icon">⏰</div><div class="empty-text">Belum ada pengajuan lembur.</div></div></td></tr>
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