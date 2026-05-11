@extends('layouts.employee')

@section('title', 'Dashboard')

@section('breadcrumb')
    <span class="current">Dashboard</span>
@endsection

@section('content')
@php
    $user = $dashboard['user'] ?? auth()->user();
@endphp

{{-- Selamat Datang --}}
<div class="page-title">Selamat datang, {{ $user->name ?? 'Employee' }} 👋</div>
<p class="page-sub">{{ now()->isoFormat('dddd, D MMMM Y') }}</p>

{{-- Stat Cards --}}
<div class="stat-grid">
    <div class="stat-card stat-primary">
        <div class="label">Hadir Bulan Ini</div>
        <div class="value">{{ $dashboard['hadir'] ?? 0 }}</div>
        <div class="sub">hari</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="label">Terlambat</div>
        <div class="value">{{ $dashboard['terlambat'] ?? 0 }}</div>
        <div class="sub">hari</div>
    </div>
    <div class="stat-card stat-danger">
        <div class="label">Alpha</div>
        <div class="value">{{ $dashboard['alpha'] ?? 0 }}</div>
        <div class="sub">hari</div>
    </div>
    <div class="stat-card">
        <div class="label">Izin Pending</div>
        <div class="value">{{ $dashboard['izin_pending'] ?? 0 }}</div>
        <div class="sub">pengajuan</div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Aksi Cepat</span>
    </div>
    <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('employee.attendance.index') }}" class="btn btn-primary">
            <i class="fas fa-fingerprint"></i> Absensi
        </a>
        <a href="{{ route('employee.permission.index') }}" class="btn btn-outline">
            <i class="fas fa-hand-paper"></i> Izin
        </a>
        <a href="{{ route('employee.leave.index') }}" class="btn btn-outline">
            <i class="fas fa-plane-departure"></i> Cuti
        </a>
        <a href="{{ route('employee.daily-report.index') }}" class="btn btn-outline">
            <i class="fas fa-clipboard-list"></i> Laporan Harian
        </a>
    </div>
</div>

@endsection