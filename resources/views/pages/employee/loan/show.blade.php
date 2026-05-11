@extends('layouts.employee')
@section('title','Detail Pinjaman')
@section('breadcrumb')
    <a href="{{ route('employee.loan.index') }}">Pinjaman</a>
    <span class="sep">/</span><span class="current">Detail</span>
@endsection
@section('content')
@php $loan = $loan['loan'] ?? $loan; $summary = $loan['summary'] ?? []; $payments = $loan['payments'] ?? []; @endphp
<div style="display:flex;gap:14px;align-items:center;margin-bottom:24px">
    <a href="{{ route('employee.loan.index') }}" class="btn btn-sm">← Kembali</a>
    <div class="page-title" style="margin:0">Detail Pinjaman</div>
</div>

<div class="two-col" style="align-items:start">
    <div>
        <div class="card">
            <div class="card-header">
                <span class="card-title">Informasi Pinjaman</span>
                @if(($loan['status']??'')==='pending') <span class="badge badge-warning">Pending</span>
                @elseif(($loan['status']??'')==='active') <span class="badge badge-info">Aktif</span>
                @elseif(($loan['status']??'')==='settled') <span class="badge badge-success">Lunas</span>
                @elseif(($loan['status']??'')==='canceled') <span class="badge badge-gray">Canceled</span>
                @endif
            </div>
            <div class="info-list">
                <div class="row"><span class="lbl">Jumlah Pinjaman</span><span class="val" style="font-size:18px;font-weight:700">Rp {{ number_format($loan['amount']??0,0,',','.') }}</span></div>
                <div class="row"><span class="lbl">Sisa Tagihan</span><span class="val" style="color:var(--c-danger)">Rp {{ number_format($loan['balance']??0,0,',','.') }}</span></div>
                <div class="row"><span class="lbl">Cicilan/Bulan</span><span class="val">Rp {{ number_format($loan['monthly_installment']??0,0,',','.') }}</span></div>
                <div class="row"><span class="lbl">Jumlah Cicilan</span><span class="val">{{ $loan['installments']??0 }}x</span></div>
                <div class="row"><span class="lbl">Jenis Bayar</span><span class="val">{{ str_replace('_',' ',ucfirst($loan['payment_type']??'—')) }}</span></div>
                @if($loan['payment_date_of_month']??null)<div class="row"><span class="lbl">Tanggal Bayar</span><span class="val">Tgl {{ $loan['payment_date_of_month'] }} tiap bulan</span></div>@endif
                <div class="row"><span class="lbl">Kategori</span><span class="val" style="text-transform:capitalize">{{ str_replace('_',' ',$loan['purpose_category']??'—') }}</span></div>
                @if($loan['approved_by']??null)<div class="row"><span class="lbl">Disetujui</span><span class="val">{{ $loan['approved_by']['name']??'—' }}</span></div>@endif
                @if($loan['approval_note']??null)<div class="row"><span class="lbl">Catatan</span><span class="val">{{ $loan['approval_note'] }}</span></div>@endif
            </div>
            {{-- Progress --}}
            @php $pct = $summary['progress_percent'] ?? (isset($loan['amount']) && $loan['amount'] > 0 ? round((($loan['amount']-$loan['balance'])/$loan['amount'])*100,1) : 0); @endphp
            <div style="margin-top:14px">
                <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--c-muted);margin-bottom:6px">
                    <span>Progress Pelunasan</span><span>{{ $pct }}%</span>
                </div>
                <div class="progress" style="height:8px"><div class="progress-fill" style="width:{{ $pct }}%"></div></div>
            </div>
        </div>

        @if($summary)
            <div class="metrics">
                <div class="metric"><div class="metric-label">Total Dibayar</div><div class="metric-val success" style="font-size:18px">Rp {{ number_format($summary['total_paid']??0,0,',','.') }}</div></div>
                <div class="metric"><div class="metric-label">Sisa Tagihan</div><div class="metric-val danger" style="font-size:18px">Rp {{ number_format($summary['remaining']??$loan['balance']??0,0,',','.') }}</div></div>
            </div>
        @endif
    </div>

    {{-- Histori Cicilan --}}
    <div class="card">
        <div class="card-title" style="margin-bottom:14px">Histori Cicilan</div>
        @if(count($payments))
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Tgl Bayar</th><th>Dibayar</th><th>Sisa Setelah</th><th>Metode</th></tr></thead>
                    <tbody>
                        @foreach($payments as $pay)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($pay['payment_date'])->isoFormat('D MMM Y') }}</td>
                                <td>Rp {{ number_format($pay['amount_paid'],0,',','.') }}</td>
                                <td>Rp {{ number_format($pay['balance_after'],0,',','.') }}</td>
                                <td style="text-transform:capitalize">{{ $pay['method'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty"><div class="empty-icon">📊</div><div class="empty-text">Belum ada pembayaran cicilan.</div></div>
        @endif
    </div>
</div>
@endsection