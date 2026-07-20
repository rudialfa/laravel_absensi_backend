@extends('layouts.app')

@section('title', 'Detail Langganan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Detail Langganan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.subscriptions.index') }}">Langganan Tenant</a>
                    </div>
                    <div class="breadcrumb-item">{{ $subscription->company->name ?? '-' }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">{{ $subscription->company->name ?? '-' }}</h2>
                <p class="section-lead">
                    Paket saat ini: <strong>{{ $subscription->plan->name ?? '-' }}</strong> &middot; Status:
                    <strong>{{ ucfirst($subscription->status) }}</strong>
                </p>

                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <small class="text-muted">Mulai</small>
                                <div class="font-weight-bold">{{ optional($subscription->started_at)->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <small class="text-muted">Berakhir</small>
                                <div class="font-weight-bold">{{ optional($subscription->expires_at)->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <small class="text-muted">Sudah Pakai Trial?</small>
                                <div class="font-weight-bold">{{ $subscription->has_used_trial ? 'Ya' : 'Belum' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <small class="text-muted">Invoice Terakhir</small>
                                <div class="font-weight-bold">#{{ $subscription->last_invoice_id ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-1">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Perpanjang Manual</h4>
                            </div>
                            <form action="{{ route('superadmin.subscriptions.extend', $subscription->id) }}" method="POST">
                                @csrf
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Jumlah Hari</label>
                                        <input type="number" name="extend_days" class="form-control" min="1"
                                            required>
                                    </div>
                                    <div class="form-group">
                                        <label>Alasan</label>
                                        <textarea name="reason" class="form-control" rows="2" required></textarea>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <button class="btn btn-primary btn-sm">Perpanjang</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Ubah Paket</h4>
                            </div>
                            <form action="{{ route('superadmin.subscriptions.change-plan', $subscription->id) }}"
                                method="POST">
                                @csrf
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Paket Baru</label>
                                        <select name="plan_id" class="form-control" required>
                                            @foreach ($plans as $plan)
                                                <option value="{{ $plan->id }}" @selected($plan->id === $subscription->plan_id)>
                                                    {{ $plan->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <div class="custom-checkbox custom-control">
                                            <input type="checkbox" name="reset_period" value="1"
                                                class="custom-control-input" id="reset_period">
                                            <label for="reset_period" class="custom-control-label">Reset masa aktif dari
                                                hari ini</label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Alasan</label>
                                        <textarea name="reason" class="form-control" rows="2" required></textarea>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <button class="btn btn-primary btn-sm">Ubah Paket</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Aksi Lain</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('superadmin.subscriptions.reactivate', $subscription->id) }}"
                                    method="POST" class="mb-3">
                                    @csrf
                                    <button class="btn btn-outline-success btn-sm btn-block">Aktifkan Kembali</button>
                                </form>

                                <form action="{{ route('superadmin.subscriptions.cancel', $subscription->id) }}"
                                    method="POST" class="confirm-delete-form">
                                    @csrf
                                    <textarea name="reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Alasan pembatalan"
                                        required></textarea>
                                    <button class="btn btn-outline-danger btn-sm btn-block confirm-delete">Batalkan
                                        Langganan</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-1">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Histori Invoice</h4>
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
                                                <th>Jatuh Tempo</th>
                                                <th>VA</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($invoices as $inv)
                                                <tr>
                                                    <td>{{ $inv->invoice_number }}</td>
                                                    <td>{{ $inv->plan->name ?? '-' }}</td>
                                                    <td>Rp{{ number_format($inv->total_amount, 0, ',', '.') }}</td>
                                                    <td>{{ ucfirst($inv->status) }}</td>
                                                    <td>{{ optional($inv->due_at)->format('d/m/Y H:i') }}</td>
                                                    <td>
                                                        @if ($inv->vaPayment)
                                                            <a
                                                                href="{{ route('superadmin.va-payments.show', $inv->vaPayment->id) }}">{{ $inv->vaPayment->va_number }}</a>
                                                        @else
                                                            -
                                                        @endif
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
                                <div class="float-right">
                                    {{ $invoices->links() }}
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
