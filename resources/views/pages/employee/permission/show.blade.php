@extends('layouts.employee')
@section('title','Detail Izin')
@section('breadcrumb')
    <a href="{{ route('employee.permission.index') }}">Izin</a>
    <span class="sep">/</span><span class="current">Detail</span>
@endsection
@section('content')
<div style="display:flex;gap:14px;align-items:center;margin-bottom:24px">
    <a href="{{ route('employee.permission.index') }}" class="btn btn-sm">← Kembali</a>
    <div class="page-title" style="margin:0">Detail Izin</div>
    @if($perm['is_approved'] !== true)
        <form method="POST" action="{{ route('employee.permission.cancel', $perm['id']) }}" onsubmit="return confirm('Batalkan izin ini?')">
            @csrf <button class="btn btn-sm btn-danger">Batalkan</button>
        </form>
    @endif
</div>
<div class="two-col" style="align-items:start;max-width:760px">
    <div class="card">
        <div class="card-title" style="margin-bottom:14px">Informasi Izin</div>
        <div class="info-list">
            <div class="row"><span class="lbl">Tanggal Izin</span><span class="val">{{ \Carbon\Carbon::parse($perm['date_permission'])->isoFormat('D MMMM Y') }}</span></div>
            <div class="row"><span class="lbl">Status</span><span class="val">
                @if($perm['is_approved']===null) <span class="badge badge-warning">Menunggu Persetujuan</span>
                @elseif($perm['is_approved']) <span class="badge badge-success">Disetujui</span>
                @else <span class="badge badge-danger">Ditolak</span>
                @endif
            </span></div>
            <div class="row"><span class="lbl">Diajukan</span><span class="val">{{ \Carbon\Carbon::parse($perm['created_at'])->isoFormat('D MMM Y, HH:mm') }}</span></div>
        </div>
        <div style="margin-top:14px">
            <div style="font-size:12px;font-weight:500;color:var(--c-muted);margin-bottom:6px">Alasan</div>
            <div style="font-size:13px;line-height:1.7;background:var(--c-bg);padding:12px;border-radius:var(--radius-sm)">{{ $perm['reason'] }}</div>
        </div>
    </div>
    @if($perm['image'])
        <div class="card">
            <div class="card-title" style="margin-bottom:12px">Bukti Pendukung</div>
            <img src="{{ asset($perm['image']) }}" style="width:100%;border-radius:var(--radius-sm);border:1px solid var(--c-border)">
        </div>
    @endif
</div>
@endsection