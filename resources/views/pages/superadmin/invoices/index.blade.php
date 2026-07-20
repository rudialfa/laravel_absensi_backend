@extends('layouts.app')

@section('title', 'Invoices')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Invoices</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Invoices</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Invoice Langganan Tenant</h2>
                <p class="section-lead">
                    Semua invoice/tagihan langganan seluruh tenant. Klik Detail untuk verifikasi manual atau tolak invoice.
                </p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Semua Invoice</h4>
                            </div>
                            <div class="card-body">

                                <div class="float-right">
                                    <form method="GET" action="{{ route('superadmin.invoices.index') }}"
                                        class="form-inline">
                                        <select name="status" class="form-control mr-2" onchange="this.form.submit()">
                                            <option value="">Semua status</option>
                                            @foreach (['pending', 'paid', 'cancelled'] as $s)
                                                <option value="{{ $s }}" @selected(request('status') === $s)>
                                                    {{ ucfirst($s) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" class="form-control mr-2" placeholder="Nama tenant"
                                            name="company" value="{{ request('company') }}">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="No. Invoice"
                                                name="invoice_number" value="{{ request('invoice_number') }}">
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
                                                <th>No. Invoice</th>
                                                <th>Tenant</th>
                                                <th>Paket</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Diterbitkan</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($invoices as $invoice)
                                                <tr>
                                                    <td>{{ $invoice->invoice_number }}</td>
                                                    <td>{{ $invoice->company->name ?? '-' }}</td>
                                                    <td>{{ $invoice->plan->name ?? '-' }}</td>
                                                    <td>Rp{{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
                                                    <td>
                                                        @php
                                                            $badge = match ($invoice->status) {
                                                                'paid' => 'success',
                                                                'pending' => 'warning',
                                                                default => 'danger',
                                                            };
                                                        @endphp
                                                        <div class="badge badge-{{ $badge }}">
                                                            {{ ucfirst($invoice->status) }}</div>
                                                    </td>
                                                    <td>{{ optional($invoice->issued_at)->format('d/m/Y H:i') }}</td>
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            <a href="{{ route('superadmin.invoices.show', $invoice->id) }}"
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-eye"></i> Detail
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center">Belum ada invoice</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $invoices->withQueryString()->links() }}
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
