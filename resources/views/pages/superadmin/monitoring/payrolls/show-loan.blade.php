@extends('layouts.app')

@section('title', 'Detail Pinjaman')

@section('main')
    @php
        $l = $loan;
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $totalPaid = $l->payments->sum('amount_paid');
        $paidPct = (float) $l->amount > 0 ? max(0, min(100, round(($totalPaid / (float) $l->amount) * 100))) : 0;
        $methodLabel = ['manual' => 'Manual (HR)', 'lump_sum' => 'Pelunasan', 'payroll' => 'Potong gaji'];
        $file = $l->attachment
            ? (str_starts_with($l->attachment, 'http')
                ? $l->attachment
                : asset($l->attachment))
            : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.monitoring.payrolls.index', ['tab' => 'loans', 'company_id' => $l->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Pinjaman</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.monitoring.payrolls.index', ['tab' => 'loans']) }}">Pinjaman</a>
                    </div>
                    <div class="breadcrumb-item">#{{ $l->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $l->user->name ?? '-' }} ·
                                    {{ $purposes[$l->purpose_category] ?? ($l->purpose_category ?: 'Pinjaman') }}</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $badge[$l->status] ?? 'light' }}">{{ $statuses[$l->status] ?? $l->status }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="text-muted small">Jumlah</div>
                                        <div class="h5 mb-0">{{ $rupiah($l->amount) }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted small">Sudah dibayar</div>
                                        <div class="h5 mb-0 text-success">{{ $rupiah($totalPaid) }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted small">Sisa</div>
                                        <div class="h5 mb-0 text-danger">{{ $rupiah($l->balance) }}</div>
                                    </div>
                                </div>
                                <div class="progress mb-3" style="height:8px">
                                    <div class="progress-bar bg-success" style="width: {{ $paidPct }}%"></div>
                                </div>

                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:180px">Cicilan</th>
                                        <td>{{ $rupiah($l->monthly_installment) }} × {{ (int) $l->installments }} bulan
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Cara bayar</th>
                                        <td>
                                            {{ $paymentType[$l->payment_type] ?? ($l->payment_type ?: '-') }}
                                            @if ($l->payment_type === 'scheduled_date' && $l->payment_date_of_month)
                                                (tiap tanggal {{ $l->payment_date_of_month }})
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Keterangan</th>
                                        <td>{!! nl2br(e($l->purpose_note ?: '-')) !!}</td>
                                    </tr>
                                    <tr>
                                        <th>Diajukan</th>
                                        <td>{{ optional($l->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Keputusan HR</th>
                                        <td>
                                            {{ $l->approvedBy->name ?? '-' }}
                                            @if ($l->approved_at)
                                                · {{ \Carbon\Carbon::parse($l->approved_at)->format('d M Y H:i') }}
                                            @endif
                                            @if ($l->approval_note)
                                                <div class="text-small text-muted">"{{ $l->approval_note }}"</div>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($file)
                                        <tr>
                                            <th>Lampiran</th>
                                            <td><a href="{{ $file }}" target="_blank"><i
                                                        class="fas fa-paperclip"></i> Buka</a></td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Pembayaran ({{ $l->payments->count() }})</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th class="pl-4">Tanggal</th>
                                                <th class="text-right">Seharusnya</th>
                                                <th class="text-right">Dibayar</th>
                                                <th class="text-right">Sisa</th>
                                                <th>Cara</th>
                                                <th class="pr-4">Dicatat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($l->payments as $pay)
                                                <tr>
                                                    <td class="pl-4">{{ $fmtDate($pay->payment_date) }}</td>
                                                    <td class="text-right">{{ $rupiah($pay->amount_expected) }}</td>
                                                    <td
                                                        class="text-right {{ (float) $pay->amount_paid < (float) $pay->amount_expected ? 'text-warning' : 'text-success' }}">
                                                        {{ $rupiah($pay->amount_paid) }}</td>
                                                    <td class="text-right">{{ $rupiah($pay->balance_after) }}</td>
                                                    <td>
                                                        {{ $methodLabel[$pay->method] ?? $pay->method }}
                                                        @if ($pay->payroll_id)
                                                            <a href="{{ route('superadmin.monitoring.payrolls.show', ['payrolls', $pay->payroll_id]) }}"
                                                                class="text-small">(payroll)</a>
                                                        @endif
                                                    </td>
                                                    <td class="pr-4 text-small">{{ $pay->recordedBy->name ?? '-' }}
                                                        @if ($pay->note)
                                                            <br><span class="text-muted">{{ $pay->note }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3">Belum ada
                                                        pembayaran</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Karyawan</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Nama</th>
                                        <td class="text-right">{{ $l->user->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Departemen</th>
                                        <td class="text-right">{{ $l->user->department ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Gaji pokok</th>
                                        <td class="text-right">
                                            {{ $rupiah($l->user->salary ?? 0) }}
                                            @if (($l->user->salary ?? 0) > 0 && $l->monthly_installment)
                                                <div class="text-small text-muted">cicilan =
                                                    {{ round(((float) $l->monthly_installment / (float) $l->user->salary) * 100) }}%
                                                    gaji</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Perusahaan</th>
                                        <td class="text-right">
                                            @if ($company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $company->id) }}">{{ $company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        @if ($canDelete)
                            <div class="card border border-danger">
                                <div class="card-body">
                                    <h6 class="text-danger mb-1">Hapus Pengajuan</h6>
                                    <p class="small text-muted">Hanya untuk pengajuan yang belum berjalan dan belum ada
                                        pembayaran.</p>
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                        data-target="#deleteModal"><i class="fas fa-trash"></i> Hapus</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.monitoring.payrolls._delete', [
        'action' => route('superadmin.monitoring.payrolls.destroy', ['loans', $l->id]),
        'label' => 'pengajuan pinjaman',
    ])
@endsection
