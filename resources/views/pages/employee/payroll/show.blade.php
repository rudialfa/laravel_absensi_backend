@extends('layouts.employee')
@section('title','Slip Gaji')
@section('breadcrumb')
    <a href="{{ route('employee.payroll.index') }}">Payroll</a>
    <span class="sep">/</span><span class="current">Slip</span>
@endsection
@section('content')
<div style="display:flex;gap:14px;align-items:center;margin-bottom:24px">
    <a href="{{ route('employee.payroll.index') }}" class="btn btn-sm">← Kembali</a>
    <div class="page-title" style="margin:0">Slip Gaji</div>
    @if(($payroll['status'] ?? '') === 'paid')
        <span class="badge badge-success">✓ Sudah Dibayar</span>
    @endif
    <button onclick="window.print()" class="btn btn-sm" style="margin-left:auto">🖨 Print</button>
</div>

<div class="card" style="max-width:620px" id="slip-area">
    {{-- Header Slip --}}
    <div style="text-align:center;padding-bottom:20px;border-bottom:1px solid var(--c-border);margin-bottom:20px">
        <div style="font-size:18px;font-weight:700">SLIP GAJI</div>
        <div style="font-size:13px;color:var(--c-muted);margin-top:4px">
            Periode: {{ isset($payroll['period_start']) ? \Carbon\Carbon::parse($payroll['period_start'])->isoFormat('D MMM') : '—' }}
            – {{ isset($payroll['period_end']) ? \Carbon\Carbon::parse($payroll['period_end'])->isoFormat('D MMM Y') : '—' }}
        </div>
    </div>

    {{-- Info Karyawan --}}
    <div class="info-list" style="margin-bottom:20px">
        <div class="row"><span class="lbl">Nama</span><span class="val">{{ auth()->user()->name }}</span></div>
        <div class="row"><span class="lbl">Jabatan</span><span class="val">{{ auth()->user()->position ?? '—' }}</span></div>
        <div class="row"><span class="lbl">Departemen</span><span class="val">{{ auth()->user()->department ?? '—' }}</span></div>
    </div>

    {{-- Komponen Gaji --}}
    <div style="margin-bottom:16px">
        <div style="font-size:12px;font-weight:600;color:var(--c-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:10px">Pendapatan</div>
        <div class="info-list">
            <div class="row"><span class="lbl">Gaji Pokok</span><span class="val">Rp {{ number_format($payroll['base_salary']??0,0,',','.') }}</span></div>
            @if($payroll['allowance'] ?? $payroll['total_allowance'] ?? 0)
                <div class="row"><span class="lbl">Tunjangan</span><span class="val">Rp {{ number_format($payroll['allowance']??$payroll['total_allowance']??0,0,',','.') }}</span></div>
            @endif
            @if($payroll['overtime_pay'] ?? 0)
                <div class="row"><span class="lbl">Upah Lembur</span><span class="val">Rp {{ number_format($payroll['overtime_pay'],0,',','.') }}</span></div>
            @endif
        </div>
    </div>

    @if($payroll['deduction']??$payroll['total_deduction']??0)
        <div style="margin-bottom:16px">
            <div style="font-size:12px;font-weight:600;color:var(--c-muted);letter-spacing:.05em;text-transform:uppercase;margin-bottom:10px">Potongan</div>
            <div class="info-list">
                <div class="row"><span class="lbl">Total Potongan</span><span class="val" style="color:var(--c-danger)">- Rp {{ number_format($payroll['deduction']??$payroll['total_deduction']??0,0,',','.') }}</span></div>
            </div>
        </div>
    @endif

    {{-- Take Home --}}
    <div style="border-top:2px solid var(--c-border);padding-top:16px;display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:15px;font-weight:600">Total Diterima</span>
        <span style="font-size:22px;font-weight:700;color:var(--c-primary)">Rp {{ number_format($payroll['net_salary']??$payroll['take_home']??0,0,',','.') }}</span>
    </div>

    <div style="margin-top:20px;padding:10px;background:var(--c-bg);border-radius:var(--radius-sm);font-size:11px;color:var(--c-hint);text-align:center">
        Dokumen ini digenerate secara otomatis oleh sistem AbsensiPro.
    </div>
</div>
@endsection

@push('styles')
<style>
@media print {
    .sidebar, .top-header, .btn, .page-sub { display:none !important; }
    .main-wrap { margin-left:0; }
    #slip-area { border:none; box-shadow:none; max-width:100%; }
}
</style>
@endpush