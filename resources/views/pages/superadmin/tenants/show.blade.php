@extends('layouts.app')

@section('title', 'Detail Tenant')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Detail Tenant</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.tenants.index') }}">Tenants</a></div>
                    <div class="breadcrumb-item">{{ $tenant->name }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $tenant->name }}</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr>
                                        <th style="width:180px">Email</th>
                                        <td>{{ $tenant->email }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tipe</th>
                                        <td>{{ ucfirst($tenant->type) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Alamat</th>
                                        <td>{{ $tenant->address }} @if ($tenant->city)
                                                , {{ $tenant->city }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Jam Kerja</th>
                                        <td>{{ $tenant->time_in }} - {{ $tenant->time_out }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jumlah Karyawan</th>
                                        <td>{{ $tenant->users_count }}</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            @if ($tenant->is_active)
                                                <span class="badge badge-success">Aktif</span>
                                            @else
                                                <span class="badge badge-danger">Disuspend</span>
                                                @if ($tenant->suspend_reason)
                                                    <br><small class="text-muted">Alasan:
                                                        {{ $tenant->suspend_reason }}</small>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer text-right">
                                @if ($tenant->is_active)
                                    <button type="button" class="btn btn-warning" data-toggle="modal"
                                        data-target="#suspendModal">
                                        <i class="fas fa-ban"></i> Suspend Tenant
                                    </button>
                                @else
                                    <form action="{{ route('superadmin.tenants.activate', $tenant->id) }}" method="POST"
                                        class="d-inline">
                                        @csrf
                                        <button class="btn btn-success"><i class="fas fa-check"></i> Aktifkan
                                            Kembali</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Langganan Aktif</h4>
                            </div>
                            <div class="card-body">
                                @if ($tenant->activeSubscription)
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <th style="width:180px">Paket</th>
                                            <td>{{ $tenant->activeSubscription->plan->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td><span
                                                    class="badge badge-info">{{ ucfirst($tenant->activeSubscription->status) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Mulai</th>
                                            <td>{{ optional($tenant->activeSubscription->started_at)->format('d M Y') }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Berakhir</th>
                                            <td>{{ optional($tenant->activeSubscription->expires_at)->format('d M Y') }}
                                            </td>
                                        </tr>
                                    </table>
                                @else
                                    <p class="text-muted mb-0">Tenant ini belum memiliki langganan.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Invoice</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>No. Invoice</th>
                                                <th>Paket</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Diterbitkan</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($tenant->invoices as $invoice)
                                                <tr>
                                                    <td>{{ $invoice->invoice_number }}</td>
                                                    <td>{{ $invoice->plan->name ?? '-' }}</td>
                                                    <td>Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
                                                    <td>
                                                        <span
                                                            class="badge badge-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'pending' ? 'warning' : 'secondary') }}">
                                                            {{ ucfirst($invoice->status) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ optional($invoice->issued_at)->format('d M Y') }}</td>
                                                    <td>
                                                        <a href="{{ route('superadmin.invoices.show', $invoice->id) }}"
                                                            class="btn btn-sm btn-info btn-icon">
                                                            <i class="fas fa-eye"></i> Detail
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center">Belum ada invoice</td>
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

    <!-- Suspend Modal -->
    <div class="modal fade" id="suspendModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.tenants.suspend', $tenant->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Suspend Tenant</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Alasan (opsional)</label>
                            <textarea name="suspend_reason" class="form-control" rows="3" placeholder="Contoh: Tunggakan pembayaran"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Suspend</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
