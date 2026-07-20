@extends('layouts.app')

@section('title', 'Monitoring VA Payment')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Monitoring VA Payment</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring VA Payment</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Monitoring VA Payment</h2>
                <p class="section-lead">
                    Pantau status pembayaran Virtual Account seluruh tenant, dan cek histori webhook bank per transaksi.
                </p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Semua VA Payment</h4>
                            </div>
                            <div class="card-body">

                                <div class="float-right">
                                    <form method="GET" action="{{ route('superadmin.va-payments.index') }}"
                                        class="form-inline">
                                        <select name="bank" class="form-control mr-2" onchange="this.form.submit()">
                                            <option value="">Semua bank</option>
                                            <option value="bca" @selected(request('bank') === 'bca')>BCA</option>
                                            <option value="mandiri" @selected(request('bank') === 'mandiri')>Mandiri</option>
                                        </select>
                                        <select name="status" class="form-control mr-2" onchange="this.form.submit()">
                                            <option value="">Semua status</option>
                                            @foreach (['pending', 'paid', 'expired', 'cancelled'] as $s)
                                                <option value="{{ $s }}" @selected(request('status') === $s)>
                                                    {{ ucfirst($s) }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari no. VA / tenant"
                                                name="search" value="{{ request('search') }}">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="clearfix mb-3"></div>

                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>No. VA</th>
                                                <th>Bank</th>
                                                <th>Tenant</th>
                                                <th>Invoice</th>
                                                <th>Jumlah</th>
                                                <th>Status</th>
                                                <th>Kadaluarsa</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($vaPayments as $va)
                                                <tr>
                                                    <td><code>{{ $va->va_number }}</code></td>
                                                    <td>{{ strtoupper($va->bank) }}</td>
                                                    <td>{{ $va->company->name ?? '-' }}</td>
                                                    <td>{{ $va->invoice->invoice_number ?? '-' }}</td>
                                                    <td>Rp{{ number_format($va->amount, 0, ',', '.') }}</td>
                                                    <td>
                                                        @php
                                                            $badge = match ($va->status) {
                                                                'paid' => 'success',
                                                                'pending' => 'warning',
                                                                default => 'danger',
                                                            };
                                                        @endphp
                                                        <div class="badge badge-{{ $badge }}">
                                                            {{ ucfirst($va->status) }}</div>
                                                    </td>
                                                    <td>{{ optional($va->expired_at)->format('d/m/Y H:i') }}</td>
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            <a href="{{ route('superadmin.va-payments.show', $va->id) }}"
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-eye"></i> Detail
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center">Belum ada data VA payment</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $vaPayments->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
