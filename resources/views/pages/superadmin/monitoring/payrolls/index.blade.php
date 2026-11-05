@extends('layouts.app')

@section('title', 'Payroll & Pinjaman')

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $hasFilter = collect(request()->only(['company_id', 'status', 'month', 'purpose', 'search']))
            ->filter()
            ->isNotEmpty();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Payroll &amp; Pinjaman</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring</div>
                    <div class="breadcrumb-item">Payroll &amp; Pinjaman</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Data gaji dan pinjaman karyawan dikelola HR tiap perusahaan. Di sini Anda bisa memantau, mengunduh,
                    dan menghapus draft atau pengajuan yang salah.
                </p>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ route('superadmin.monitoring.payrolls.index', array_filter(['tab' => $key, 'company_id' => request('company_id')])) }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>

                {{-- Ringkasan --}}
                <div class="row">
                    @if ($tab === 'payrolls')
                        @foreach ([['Slip Gaji', number_format((int) $summary->total), 'primary', 'fa-file-invoice-dollar'], ['Draft / Disetujui', ((int) $summary->draft) . ' / ' . ((int) $summary->approved), 'warning', 'fa-hourglass-half'], ['Total Gaji Bersih', $rupiah($summary->net_total), 'info', 'fa-coins'], ['Sudah Dibayar', $rupiah($summary->net_paid), 'success', 'fa-check-circle']] as [$label, $value, $color, $icon])
                            <div class="col-6 col-lg-3">
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i>
                                    </div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>{{ $label }}</h4>
                                        </div>
                                        <div class="card-body" style="font-size:17px">{{ $value }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        @foreach ([['Pengajuan', number_format((int) $summary->total), 'primary', 'fa-hand-holding-usd', null], ['Menunggu HR', number_format((int) $summary->pending), 'warning', 'fa-hourglass-half', 'pending'], ['Sisa Pinjaman Berjalan', $rupiah($summary->outstanding), 'danger', 'fa-balance-scale', 'running'], ['Total Dicairkan', $rupiah($summary->disbursed), 'success', 'fa-coins', null]] as [$label, $value, $color, $icon, $filter])
                            <div class="col-6 col-lg-3">
                                @if ($filter)
                                    <a href="{{ route('superadmin.monitoring.payrolls.index', array_filter(['tab' => 'loans', 'company_id' => request('company_id'), 'status' => $filter])) }}"
                                        class="text-decoration-none">
                                @endif
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i>
                                    </div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>{{ $label }}</h4>
                                        </div>
                                        <div class="card-body" style="font-size:17px">{{ $value }}</div>
                                    </div>
                                </div>
                                @if ($filter)
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.monitoring.payrolls.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control">
                                        <option value="">Semua Perusahaan</option>
                                        @foreach ($companies as $c)
                                            <option value="{{ $c->id }}" @selected((string) request('company_id') === (string) $c->id)>
                                                {{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        @if ($tab === 'loans')
                                            <option value="running" @selected(request('status') === 'running')>Sedang berjalan</option>
                                        @endif
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected(request('status') === $value)>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($tab === 'payrolls')
                                    <div class="col-md-2 mb-3">
                                        <select name="month" class="form-control">
                                            <option value="">Semua Periode</option>
                                            @foreach ($months as $value => $label)
                                                <option value="{{ $value }}" @selected(request('month') === $value)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <div class="col-md-2 mb-3">
                                        <select name="purpose" class="form-control">
                                            <option value="">Semua Tujuan</option>
                                            @foreach ($purposes as $value => $label)
                                                <option value="{{ $value }}" @selected(request('purpose') === $value)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md-3 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control" placeholder="Nama karyawan">
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block"><i class="fas fa-search"></i></button>
                                </div>
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('superadmin.monitoring.payrolls.export', request()->query() + ['tab' => $tab]) }}"
                                        class="btn btn-outline-success btn-block" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.monitoring.payrolls.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            @if ($tab === 'payrolls')
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Karyawan</th>
                                            <th>Periode</th>
                                            <th class="text-right">Gaji Pokok</th>
                                            <th class="text-right">+ Tambahan</th>
                                            <th class="text-right">− Potongan</th>
                                            <th class="text-right">Bersih</th>
                                            <th>Status</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $p)
                                            <tr>
                                                <td>
                                                    <strong>{{ $p->user->name ?? '(user terhapus)' }}</strong>
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$p->company_id] ?? '-' }}</div>
                                                </td>
                                                <td class="text-small">{{ $fmtDate($p->period_start) }}<br>s/d
                                                    {{ $fmtDate($p->period_end) }}</td>
                                                <td class="text-right">{{ $rupiah($p->base_salary) }}</td>
                                                <td class="text-right text-success">
                                                    {{ $rupiah((float) $p->allowance + (float) $p->overtime_pay + (float) $p->bonus) }}
                                                </td>
                                                <td class="text-right text-danger">{{ $rupiah($p->deductions) }}</td>
                                                <td class="text-right"><strong>{{ $rupiah($p->net_pay) }}</strong></td>
                                                <td><span
                                                        class="badge badge-{{ $badge[$p->status] ?? 'light' }}">{{ $statuses[$p->status] ?? $p->status }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('superadmin.monitoring.payrolls.show', ['payrolls', $p->id]) }}"
                                                        class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Belum ada data
                                                    payroll</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            @else
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Karyawan</th>
                                            <th>Tujuan</th>
                                            <th class="text-right">Jumlah</th>
                                            <th class="text-right">Sisa</th>
                                            <th>Cicilan</th>
                                            <th>Status</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $l)
                                            @php $paidPct = (float) $l->amount > 0 ? max(0, min(100, round((1 - (float) $l->balance / (float) $l->amount) * 100))) : 0; @endphp
                                            <tr>
                                                <td>
                                                    <strong>{{ $l->user->name ?? '(user terhapus)' }}</strong>
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$l->company_id] ?? '-' }} ·
                                                        {{ $fmtDate($l->created_at) }}</div>
                                                </td>
                                                <td>{{ $purposes[$l->purpose_category] ?? ($l->purpose_category ?: '-') }}
                                                </td>
                                                <td class="text-right">{{ $rupiah($l->amount) }}</td>
                                                <td class="text-right">
                                                    {{ $rupiah($l->balance) }}
                                                    @if (in_array($l->status, ['active', 'approved', 'paid']))
                                                        <div class="progress mt-1" style="height:4px">
                                                            <div class="progress-bar bg-success"
                                                                style="width: {{ $paidPct }}%"></div>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-small">
                                                    {{ $rupiah($l->monthly_installment) }} × {{ (int) $l->installments }}
                                                    <div class="text-muted">{{ $l->payments_count }} kali bayar</div>
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $badge[$l->status] ?? 'light' }}">{{ $statuses[$l->status] ?? $l->status }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('superadmin.monitoring.payrolls.show', ['loans', $l->id]) }}"
                                                        class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Belum ada pinjaman
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>

                    @if ($items->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">Menampilkan {{ $items->firstItem() }}–{{ $items->lastItem() }} dari
                                {{ $items->total() }}</small>
                            {{ $items->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
