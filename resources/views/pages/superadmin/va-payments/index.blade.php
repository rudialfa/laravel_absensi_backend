@extends('layouts.app')

@section('title', 'VA Payment')

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $badge = ['pending' => 'warning', 'paid' => 'success', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $hasFilter = collect(request()->only(['search', 'bank', 'status', 'from', 'to']))->filter()->isNotEmpty();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>VA Payment</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">VA Payment</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <h2 class="section-title">Monitoring Virtual Account</h2>
                <p class="section-lead">
                    VA dibuat otomatis lewat Midtrans saat tenant memilih paket, atau dari menu
                    <a href="{{ route('superadmin.invoices.index') }}">Invoice</a>. Di sini Anda bisa memantau, mengecek status ke Midtrans,
                    menandai lunas, atau menutup VA.
                </p>

                @if ($staleCount > 0)
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i> {{ $staleCount }} VA sudah lewat masa berlaku tapi masih berstatus menunggu bayar
                        (notifikasi dari Midtrans belum masuk).
                        <a href="{{ route('superadmin.va-payments.index', ['status' => 'stale']) }}" class="alert-link">Lihat</a>
                    </div>
                @endif
                @if ($mismatchCount > 0)
                    <div class="alert alert-light border">
                        <i class="fas fa-exclamation-circle text-warning"></i> {{ $mismatchCount }} VA statusnya berbeda dengan status invoice-nya.
                        Buka detailnya lalu klik <b>Cek Status di Midtrans</b> untuk menyamakan.
                    </div>
                @endif

                <div class="row">
                    @foreach ($statuses as $key => $label)
                        @php $row = $summary[$key] ?? null; @endphp
                        <div class="col-6 col-lg-3">
                            <a href="{{ route('superadmin.va-payments.index', array_merge(request()->except(['status', 'page']), ['status' => $key])) }}" class="text-decoration-none">
                                <div class="card card-statistic-1 {{ request('status') === $key ? 'border border-primary' : '' }}">
                                    <div class="card-icon bg-{{ $badge[$key] }}"><i class="fas fa-money-check-alt"></i></div>
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
                        <form method="GET" action="{{ route('superadmin.va-payments.index') }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="No. VA, tenant, invoice, ref">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="bank" class="form-control">
                                        <option value="">Semua Bank</option>
                                        @foreach ($banks as $bank)
                                            <option value="{{ $bank }}" @selected(request('bank') === $bank)>{{ strtoupper($bank) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                        @endforeach
                                        <option value="stale" @selected(request('status') === 'stale')>Lewat masa berlaku</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <input type="date" name="from" value="{{ request('from') }}" class="form-control" title="Dibuat dari">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <input type="date" name="to" value="{{ request('to') }}" class="form-control" title="Dibuat sampai">
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block" title="Cari"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3">
                                    <a href="{{ route('superadmin.va-payments.index') }}" class="text-small"><i class="fas fa-times"></i> Hapus filter</a>
                                </div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>No. VA</th>
                                        <th>Tenant</th>
                                        <th>Invoice</th>
                                        <th class="text-right">Nominal</th>
                                        <th>Status</th>
                                        <th>Berlaku s/d</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($vaPayments as $va)
                                        @php
                                            $stale = $va->status === 'pending' && $va->expired_at && $va->expired_at->isPast();
                                            $mismatch = $va->invoice && $va->invoice->status !== $va->status;
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('superadmin.va-payments.show', $va->id) }}"><code>{{ $va->va_number }}</code></a>
                                                <div class="text-small text-muted">{{ strtoupper($va->bank) }} · {{ $va->logs_count }} callback</div>
                                            </td>
                                            <td>{{ $va->company->name ?? '-' }}</td>
                                            <td>
                                                @if ($va->invoice)
                                                    <a href="{{ route('superadmin.invoices.show', $va->invoice_id) }}">{{ $va->invoice->invoice_number }}</a>
                                                    @if ($mismatch)
                                                        <div class="text-small text-warning"><i class="fas fa-exclamation-circle"></i> invoice: {{ $statuses[$va->invoice->status] ?? $va->invoice->status }}</div>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-right">{{ $rupiah($va->amount) }}</td>
                                            <td>
                                                <span class="badge badge-{{ $badge[$va->status] ?? 'secondary' }}">{{ $statuses[$va->status] ?? ucfirst($va->status) }}</span>
                                                @if ($va->paid_at)
                                                    <div class="text-small text-success">{{ $va->paid_at->format('d M Y H:i') }}</div>
                                                @endif
                                            </td>
                                            <td class="text-small {{ $stale ? 'text-danger' : '' }}">
                                                {{ optional($va->expired_at)->format('d M Y H:i') }}
                                                @if ($stale)
                                                    <br>sudah lewat
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.va-payments.show', $va->id) }}" class="btn btn-sm btn-info" title="Detail"><i class="fas fa-eye"></i></a>
                                                @if (!$va->isPaid() && $va->invoice && !$va->invoice->isPaid())
                                                    <form action="{{ route('superadmin.va-payments.check-status', $va->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button class="btn btn-sm btn-primary" title="Cek status di Midtrans"><i class="fas fa-sync-alt"></i></button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada VA</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($vaPayments->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">Menampilkan {{ $vaPayments->firstItem() }}–{{ $vaPayments->lastItem() }} dari {{ $vaPayments->total() }}</small>
                            {{ $vaPayments->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
