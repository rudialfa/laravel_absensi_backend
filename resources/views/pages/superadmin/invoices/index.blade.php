@extends('layouts.app')

@section('title', 'Invoice')

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $badge = ['pending' => 'warning', 'paid' => 'success', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $filterKeys = ['search', 'status', 'plan_id', 'company_id', 'from', 'to'];
        $hasFilter = collect(request()->only($filterKeys))->filter()->isNotEmpty();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Invoice</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.invoices.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Buat Invoice</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Invoice</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($overdueCount > 0 && request('status') !== 'overdue')
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i> {{ $overdueCount }} invoice sudah lewat jatuh tempo tapi masih berstatus menunggu bayar.
                        <a href="{{ route('superadmin.invoices.index', ['status' => 'overdue']) }}" class="alert-link">Lihat</a>
                    </div>
                @endif

                {{-- Ringkasan sesuai filter --}}
                <div class="row">
                    @foreach ($statuses as $key => $label)
                        @php $row = $summary[$key] ?? null; @endphp
                        <div class="col-6 col-lg-3">
                            <a href="{{ route('superadmin.invoices.index', array_merge(request()->except(['status', 'page']), ['status' => $key])) }}" class="text-decoration-none">
                                <div class="card card-statistic-1 {{ request('status') === $key ? 'border border-primary' : '' }}">
                                    <div class="card-icon bg-{{ $badge[$key] }}"><i class="fas fa-file-invoice"></i></div>
                                    <div class="card-wrap">
                                        <div class="card-header"><h4>{{ $label }}</h4></div>
                                        <div class="card-body" style="font-size:18px">
                                            {{ $row->total ?? 0 }}
                                            <div class="text-small text-muted font-weight-normal">{{ $rupiah($row->amount ?? 0) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.invoices.index') }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="No. invoice atau nama tenant">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                        @endforeach
                                        <option value="overdue" @selected(request('status') === 'overdue')>Lewat jatuh tempo</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="plan_id" class="form-control">
                                        <option value="">Semua Paket</option>
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <input type="date" name="from" value="{{ request('from') }}" class="form-control" title="Diterbitkan dari">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <input type="date" name="to" value="{{ request('to') }}" class="form-control" title="Diterbitkan sampai">
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block" title="Cari"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    @if ($hasFilter)
                                        <a href="{{ route('superadmin.invoices.index') }}" class="text-small"><i class="fas fa-times"></i> Hapus filter</a>
                                    @endif
                                </div>
                                <a href="{{ route('superadmin.invoices.export', request()->only($filterKeys)) }}" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-file-excel"></i> Unduh CSV
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>No. Invoice</th>
                                        <th>Tenant</th>
                                        <th>Paket</th>
                                        <th class="text-right">Total</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($invoices as $invoice)
                                        @php $overdue = $invoice->status === 'pending' && $invoice->due_at && $invoice->due_at->isPast(); @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('superadmin.invoices.show', $invoice->id) }}" class="font-weight-bold">{{ $invoice->invoice_number }}</a>
                                                @if ($invoice->vaPayment)
                                                    <div class="text-small text-muted">VA {{ strtoupper($invoice->vaPayment->bank) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($invoice->company)
                                                    <a href="{{ route('superadmin.tenants.show', $invoice->company_id) }}">{{ $invoice->company->name }}</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $invoice->plan->name ?? '-' }}</td>
                                            <td class="text-right">
                                                {{ $rupiah($invoice->total_amount) }}
                                                @if ((float) $invoice->discount_amount > 0)
                                                    <div class="text-small text-muted">diskon {{ $rupiah($invoice->discount_amount) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $badge[$invoice->status] ?? 'secondary' }}">{{ $statuses[$invoice->status] ?? ucfirst($invoice->status) }}</span>
                                                @if ($overdue)
                                                    <div class="text-small text-danger">lewat jatuh tempo</div>
                                                @endif
                                            </td>
                                            <td class="text-small">
                                                {{ optional($invoice->issued_at)->format('d M Y H:i') }}
                                                @if ($invoice->paid_at)
                                                    <br><span class="text-success">Lunas {{ $invoice->paid_at->format('d M Y') }}</span>
                                                @elseif ($invoice->status === 'pending')
                                                    <br>Tempo {{ optional($invoice->due_at)->format('d M H:i') }}
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.invoices.show', $invoice->id) }}" class="btn btn-sm btn-info" title="Detail"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('superadmin.invoices.print', $invoice->id) }}" target="_blank" class="btn btn-sm btn-light" title="Cetak"><i class="fas fa-print"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada invoice</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($invoices->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">Menampilkan {{ $invoices->firstItem() }}–{{ $invoices->lastItem() }} dari {{ $invoices->total() }}</small>
                            {{ $invoices->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
