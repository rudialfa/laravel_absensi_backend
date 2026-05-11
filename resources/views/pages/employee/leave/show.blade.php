{{-- leave/show.blade.php --}}
@extends('layouts.employee')
@section('title','Detail Cuti')
@section('breadcrumb')
    <a href="{{ route('employee.leave.index') }}">Cuti</a>
    <span class="sep">/</span><span class="current">Detail</span>
@endsection
@section('content')
@php $typeLabel=['annual'=>'Tahunan','sick'=>'Sakit','maternity'=>'Melahirkan','important'=>'Kepentingan','other'=>'Lainnya']; @endphp
<div style="display:flex;align-items:center;gap:14px;margin-bottom:24px">
    <a href="{{ route('employee.leave.index') }}" class="btn btn-sm">← Kembali</a>
    <div class="page-title" style="margin:0">Detail Pengajuan Cuti</div>
    @if($leave['status']==='pending')
        <form method="POST" action="{{ route('employee.leave.cancel', $leave['id']) }}" onsubmit="return confirm('Batalkan pengajuan?')">
            @csrf <button class="btn btn-sm btn-danger">Batalkan</button>
        </form>
    @endif
</div>
<div class="card" style="max-width:560px">
    <div class="info-list">
        <div class="row"><span class="lbl">Jenis</span><span class="val">{{ $typeLabel[$leave['type']] ?? $leave['type'] }}</span></div>
        <div class="row"><span class="lbl">Tanggal Mulai</span><span class="val">{{ \Carbon\Carbon::parse($leave['start_date'])->isoFormat('D MMMM Y') }}</span></div>
        <div class="row"><span class="lbl">Tanggal Selesai</span><span class="val">{{ \Carbon\Carbon::parse($leave['end_date'])->isoFormat('D MMMM Y') }}</span></div>
        <div class="row"><span class="lbl">Durasi</span><span class="val">{{ \Carbon\Carbon::parse($leave['start_date'])->diffInDays(\Carbon\Carbon::parse($leave['end_date'])) + 1 }} hari</span></div>
        <div class="row"><span class="lbl">Status</span><span class="val">
            @if($leave['status']==='pending') <span class="badge badge-warning">Pending</span>
            @elseif($leave['status']==='approved') <span class="badge badge-success">Approved</span>
            @elseif($leave['status']==='rejected') <span class="badge badge-danger">Rejected</span>
            @elseif($leave['status']==='canceled') <span class="badge badge-gray">Canceled</span>
            @endif
        </span></div>
        @if($leave['approver'])<div class="row"><span class="lbl">Disetujui oleh</span><span class="val">{{ $leave['approver']['name'] }}</span></div>@endif
        @if($leave['reason'])<div class="row"><span class="lbl">Alasan</span><span class="val">{{ $leave['reason'] }}</span></div>@endif
        <div class="row"><span class="lbl">Diajukan</span><span class="val">{{ \Carbon\Carbon::parse($leave['created_at'])->isoFormat('D MMM Y, HH:mm') }}</span></div>
    </div>
</div>
@endsection