@extends('layouts.employee')
@section('title','Detail Lembur')
@section('breadcrumb')
    <a href="{{ route('employee.overtime.index') }}">Lembur</a>
    <span class="sep">/</span><span class="current">Detail</span>
@endsection
@section('content')
<div style="display:flex;gap:14px;align-items:center;margin-bottom:24px">
    <a href="{{ route('employee.overtime.index') }}" class="btn btn-sm">← Kembali</a>
    <div class="page-title" style="margin:0">Detail Lembur</div>
    @if($overtime['status']==='pending')
        <form method="POST" action="{{ route('employee.overtime.cancel', $overtime['id']) }}" onsubmit="return confirm('Batalkan?')">
            @csrf <button class="btn btn-sm btn-danger">Batalkan</button>
        </form>
    @endif
</div>
<div class="two-col" style="align-items:start;max-width:760px">
    <div class="card">
        <div class="card-title" style="margin-bottom:14px">Informasi Lembur</div>
        <div class="info-list">
            <div class="row"><span class="lbl">Tanggal</span><span class="val">{{ \Carbon\Carbon::parse($overtime['date'])->isoFormat('D MMMM Y') }}</span></div>
            <div class="row"><span class="lbl">Jam Mulai</span><span class="val" style="font-family:'DM Mono',monospace">{{ substr($overtime['start_time'],0,5) }}</span></div>
            <div class="row"><span class="lbl">Jam Selesai</span><span class="val" style="font-family:'DM Mono',monospace">{{ substr($overtime['end_time'],0,5) }}</span></div>
            <div class="row"><span class="lbl">Durasi</span><span class="val">{{ $overtime['minutes'] }} menit ({{ floor($overtime['minutes']/60) }}j {{ $overtime['minutes']%60 }}m)</span></div>
            <div class="row"><span class="lbl">Status</span><span class="val">
                @if($overtime['status']==='pending') <span class="badge badge-warning">Pending</span>
                @elseif($overtime['status']==='approved') <span class="badge badge-success">Approved</span>
                @elseif($overtime['status']==='rejected') <span class="badge badge-danger">Rejected</span>
                @elseif($overtime['status']==='canceled') <span class="badge badge-gray">Canceled</span>
                @endif
            </span></div>
            @if($overtime['approver'])<div class="row"><span class="lbl">Disetujui</span><span class="val">{{ $overtime['approver']['name'] }}</span></div>@endif
            @if($overtime['reason'])<div class="row"><span class="lbl">Alasan</span><span class="val">{{ $overtime['reason'] }}</span></div>@endif
        </div>
    </div>
    @if($overtime['evidence_image'])
        <div class="card">
            <div class="card-title" style="margin-bottom:12px">Bukti Lembur</div>
            <img src="{{ $overtime['evidence_image'] }}" style="width:100%;border-radius:var(--radius-sm);border:1px solid var(--c-border)">
        </div>
    @endif
</div>
@endsection