@extends('layouts.employee')
@section('title','Pengajuan Izin')
@section('breadcrumb')<span class="current">Izin</span>@endsection
@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <div class="page-title">Pengajuan Izin</div>
    <a href="{{ route('employee.permission.create') }}" class="btn btn-primary">+ Ajukan Izin</a>
</div>
<div class="page-sub">Kelola semua pengajuan izin tidak masuk kerja.</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Tanggal Izin</th><th>Alasan</th><th>Bukti</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse($data['data'] ?? $data as $p)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($p['date_permission'])->isoFormat('D MMM Y') }}</td>
                        <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $p['reason'] }}</td>
                        <td>
                            @if($p['image'])
                                <a href="{{ asset($p['image']) }}" target="_blank" class="btn btn-sm">Lihat Foto</a>
                            @else
                                <span style="color:var(--c-hint);font-size:12px">—</span>
                            @endif
                        </td>
                        <td>
                            @if($p['is_approved'] === null) <span class="badge badge-warning">Menunggu</span>
                            @elseif($p['is_approved']) <span class="badge badge-success">Disetujui</span>
                            @else <span class="badge badge-danger">Ditolak</span>
                            @endif
                        </td>
                        <td style="display:flex;gap:6px">
                            <a href="{{ route('employee.permission.show', $p['id']) }}" class="btn btn-sm">Lihat</a>
                            @if($p['is_approved'] !== true)
                                <form method="POST" action="{{ route('employee.permission.cancel', $p['id']) }}" onsubmit="return confirm('Batalkan izin ini?')">
                                    @csrf <button class="btn btn-sm btn-danger">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty"><div class="empty-icon">📝</div><div class="empty-text">Belum ada pengajuan izin.</div></div></td></tr>
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