@extends('layouts.app')

@section('title', 'Superadmin Dashboard')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Dashboard</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Ringkasan Sistem</h2>
                <p class="section-lead">Ringkasan tenant, langganan, dan invoice di seluruh platform.</p>

                <div class="row">
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card card-statistic-1">
                            <div class="card-body">
                                <h4>Total Tenant</h4>
                                <h3>{{ $totalTenants }}</h3>
                                <small class="text-muted">{{ $activeTenants }} aktif &middot; {{ $suspended }}
                                    disuspend</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card card-statistic-1">
                            <div class="card-body">
                                <h4>Langganan Aktif</h4>
                                <h3>{{ $activeSubs }}</h3>
                                <small class="text-muted">{{ $trialSubs }} trial &middot; {{ $graceSubs }}
                                    grace</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card card-statistic-1">
                            <div class="card-body">
                                <h4>Invoice Pending</h4>
                                <h3>{{ $pendingInvoices }}</h3>
                                <small class="text-muted">{{ $expiredSubs }} langganan expired</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card card-statistic-1">
                            <div class="card-body">
                                <h4>Pendapatan Bulan Ini</h4>
                                <h3>Rp {{ number_format($revenueThisMonth, 0, ',', '.') }}</h3>
                                <small class="text-muted">dari invoice berstatus paid</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Invoice Terbaru</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>No. Invoice</th>
                                                <th>Tenant</th>
                                                <th>Paket</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($latestInvoices as $invoice)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('superadmin.invoices.show', $invoice->id) }}">
                                                            {{ $invoice->invoice_number }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $invoice->company->name ?? '-' }}</td>
                                                    <td>{{ $invoice->plan->name ?? '-' }}</td>
                                                    <td>
                                                        <span
                                                            class="badge badge-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'pending' ? 'warning' : 'secondary') }}">
                                                            {{ ucfirst($invoice->status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center">Belum ada invoice</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Langganan Akan Berakhir (&le; 3 hari)</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Paket</th>
                                                <th>Berakhir</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($expiringSoon as $sub)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('superadmin.tenants.show', $sub->company_id) }}">
                                                            {{ $sub->company->name ?? '-' }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $sub->plan->name ?? '-' }}</td>
                                                    <td>{{ $sub->expires_at->format('d M Y') }}</td>
                                                    <td>
                                                        <span
                                                            class="badge badge-{{ $sub->status === 'grace' ? 'danger' : 'info' }}">
                                                            {{ ucfirst($sub->status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center">Tidak ada langganan yang akan
                                                        berakhir</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
