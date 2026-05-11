@extends('layouts.employee')
@section('title','Pengajuan Cuti')
@section('breadcrumb')<span class="current">Cuti</span>@endsection
@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <div class="page-title">Pengajuan Cuti</div>
    <a href="{{ route('employee.leave.create') }}" class="btn btn-primary">+ Ajukan Cuti</a>
</div>
<div class="page-sub">Kelola semua pengajuan cuti kamu di sini.</div>

{{-- Filter --}}
<div class="card" style="padding:14px 20px;margin-bottom:16px">
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="min-width:130px">
            <label class="lbl">Status</label>
            <select name="status">
                <option value="">Semua</option>
                @foreach(['pending','approved','rejected','canceled'] as $s)
                    <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="lbl">Dari</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>
        <div class="form-group">
            <label class="lbl">Sampai</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('employee.leave.index') }}" class="btn btn-sm">Reset</a>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Jenis</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Status</th><th>Disetujui oleh</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @php $typeLabel=['annual'=>'Tahunan','sick'=>'Sakit','maternity'=>'Melahirkan','important'=>'Kepentingan','other'=>'Lainnya']; @endphp
                @forelse($data['data'] ?? $data as $l)
                    <tr>
                        <td>{{ $typeLabel[$l['type']] ?? $l['type'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($l['start_date'])->isoFormat('D MMM Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($l['end_date'])->isoFormat('D MMM Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($l['start_date'])->diffInDays(\Carbon\Carbon::parse($l['end_date'])) + 1 }} hari</td>
                        <td>
                            @if($l['status']==='pending') <span class="badge badge-warning">Pending</span>
                            @elseif($l['status']==='approved') <span class="badge badge-success">Approved</span>
                            @elseif($l['status']==='rejected') <span class="badge badge-danger">Rejected</span>
                            @elseif($l['status']==='canceled') <span class="badge badge-gray">Canceled</span>
                            @endif
                        </td>
                        <td>{{ $l['approver']['name'] ?? '—' }}</td>
                        <td style="display:flex;gap:6px">
                            <a href="{{ route('employee.leave.show', $l['id']) }}" class="btn btn-sm">Lihat</a>
                            @if($l['status']==='pending')
                                <form method="POST" action="{{ route('employee.leave.cancel', $l['id']) }}" onsubmit="return confirm('Batalkan pengajuan cuti ini?')">
                                    @csrf
                                    <button class="btn btn-sm btn-danger">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty"><div class="empty-icon">🏖️</div><div class="empty-text">Belum ada pengajuan cuti.</div></div></td></tr>
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