@extends('layouts.employee')
@section('title','Detail Catatan')
@section('breadcrumb')
    <a href="{{ route('employee.notes.index') }}">Catatan</a>
    <span class="sep">/</span><span class="current">Detail</span>
@endsection
@section('content')
<div style="display:flex;gap:14px;align-items:center;margin-bottom:24px">
    <a href="{{ route('employee.notes.index') }}" class="btn btn-sm">← Kembali</a>
    <div class="page-title" style="margin:0">{{ $note['title'] }}</div>
    @if($note['type']==='warning') <span class="badge badge-danger">Peringatan</span>
    @elseif($note['type']==='praise') <span class="badge badge-success">Pujian</span>
    @elseif($note['type']==='performance') <span class="badge badge-info">Performa</span>
    @elseif($note['type']==='absence') <span class="badge badge-warning">Absensi</span>
    @else <span class="badge badge-gray">Umum</span>
    @endif
</div>
<div class="card" style="max-width:620px">
    <div style="font-size:14px;line-height:1.8;margin-bottom:20px">{{ $note['note'] }}</div>
    <div class="info-list">
        <div class="row"><span class="lbl">Dari</span><span class="val">{{ $note['creator']['name'] ?? 'HR' }}</span></div>
        <div class="row"><span class="lbl">Tanggal</span><span class="val">{{ \Carbon\Carbon::parse($note['created_at'])->isoFormat('D MMMM Y, HH:mm') }}</span></div>
        <div class="row"><span class="lbl">Status Baca</span><span class="val"><span class="badge {{ $note['is_read']?'badge-success':'badge-warning' }}">{{ $note['is_read']?'Sudah dibaca':'Belum dibaca' }}</span></span></div>
    </div>
</div>
@endsection