@extends('layouts.app')

@section('title', 'Detail VA Payment')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Detail VA Payment</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.va-payments.index') }}">Monitoring VA
                            Payment</a></div>
                    <div class="breadcrumb-item">{{ $vaPayment->va_number }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">VA {{ $vaPayment->va_number }} <small
                        class="text-muted">({{ strtoupper($vaPayment->bank) }})</small></h2>
                <p class="section-lead">
                    Tenant: {{ $vaPayment->company->name ?? '-' }} &middot; Invoice:
                    {{ $vaPayment->invoice->invoice_number ?? '-' }}
                </p>

                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <small class="text-muted">Jumlah</small>
                                <div class="font-weight-bold">Rp{{ number_format($vaPayment->amount, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <small class="text-muted">Status</small>
                                <div class="font-weight-bold">{{ ucfirst($vaPayment->status) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <small class="text-muted">Kadaluarsa</small>
                                <div class="font-weight-bold">{{ optional($vaPayment->expired_at)->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <small class="text-muted">Dibayar</small>
                                <div class="font-weight-bold">
                                    {{ optional($vaPayment->paid_at)->format('d/m/Y H:i') ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($vaPayment->status !== 'paid')
                    <div class="row mt-1">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Override Manual: Tandai Lunas</h4>
                                </div>
                                <form action="{{ route('superadmin.va-payments.mark-paid', $vaPayment->id) }}"
                                    method="POST">
                                    @csrf
                                    <div class="card-body">
                                        <p class="text-muted">Gunakan hanya jika sudah ada bukti transfer manual dan webhook
                                            bank tidak masuk. Aksi ini akan otomatis memperpanjang langganan tenant.</p>
                                        <textarea name="reason" class="form-control mb-2" rows="2" placeholder="Alasan / referensi bukti transfer"
                                            required></textarea>
                                    </div>
                                    <div class="card-footer text-right">
                                        <button class="btn btn-warning btn-sm">Tandai Lunas Manual</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row mt-1">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Timeline Webhook Bank</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>Waktu</th>
                                                <th>Tipe Event</th>
                                                <th>Sukses?</th>
                                                <th>HTTP Code</th>
                                                <th>IP</th>
                                                <th>Error</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($logs as $log)
                                                <tr>
                                                    <td>{{ optional($log->received_at)->format('d/m/Y H:i:s') }}</td>
                                                    <td>{{ $log->event_type }}</td>
                                                    <td>
                                                        <div
                                                            class="badge badge-{{ $log->is_success ? 'success' : 'danger' }}">
                                                            {{ $log->is_success ? 'Ya' : 'Tidak' }}
                                                        </div>
                                                    </td>
                                                    <td>{{ $log->response_http_code ?? '-' }}</td>
                                                    <td>{{ $log->ip_address ?? '-' }}</td>
                                                    <td>{{ $log->error_message ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center">Belum ada log webhook</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-muted small mb-0">Butuh lihat raw JSON payload per baris? Tambahkan modal
                                    detail yang menampilkan <code>request_payload</code> &amp;
                                    <code>response_payload</code>.
                                </p>
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
