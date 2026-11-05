@extends('layouts.app')

@section('title', 'Detail Log Callback')

@section('main')
    @php
        $json = fn($v) => $v === null
            ? '-'
            : json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $va = $log->vaPayment;
        $payload = is_array($log->request_payload) ? $log->request_payload : [];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('superadmin.va-logs.index') }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Log Callback</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.va-logs.index') }}">Log Callback</a></div>
                    <div class="breadcrumb-item">#{{ $log->id }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Log #{{ $log->id }}</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $log->is_success ? 'success' : 'danger' }}">{{ $log->is_success ? 'Berhasil' : 'Gagal' }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:110px">Diterima</th>
                                        <td>{{ optional($log->received_at)->format('d M Y H:i:s') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Bank</th>
                                        <td>{{ strtoupper($log->bank) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Event</th>
                                        <td>{{ $events[$log->event_type] ?? $log->event_type }}</td>
                                    </tr>
                                    <tr>
                                        <th>HTTP balasan</th>
                                        <td>{{ $log->response_http_code ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>IP pengirim</th>
                                        <td>{{ $log->ip_address ?: '-' }}</td>
                                    </tr>
                                    @if (!empty($payload['transaction_status']))
                                        <tr>
                                            <th>Status</th>
                                            <td><code>{{ $payload['transaction_status'] }}</code></td>
                                        </tr>
                                    @endif
                                    @if (!empty($payload['transaction_id']))
                                        <tr>
                                            <th>Transaction ID</th>
                                            <td class="text-small">{{ $payload['transaction_id'] }}</td>
                                        </tr>
                                    @endif
                                    @if ($log->error_message)
                                        <tr>
                                            <th>Error</th>
                                            <td class="text-danger">{{ $log->error_message }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Terhubung ke</h4>
                            </div>
                            <div class="card-body">
                                @if ($va)
                                    <p class="mb-1">VA <a
                                            href="{{ route('superadmin.va-payments.show', $va->id) }}"><code>{{ $va->va_number }}</code></a>
                                        ({{ strtoupper($va->bank) }})</p>
                                    @if ($va->invoice)
                                        <p class="mb-1">Invoice <a
                                                href="{{ route('superadmin.invoices.show', $va->invoice_id) }}">{{ $va->invoice->invoice_number }}</a>
                                        </p>
                                    @endif
                                    <p class="mb-0">Tenant {{ $va->company->name ?? '-' }}</p>
                                @else
                                    <p class="text-warning mb-1"><i class="fas fa-question-circle"></i> Tidak cocok dengan
                                        VA mana pun.</p>
                                    <p class="text-muted small mb-0">
                                        Biasanya karena order_id ({{ $payload['order_id'] ?? '-' }}) tidak ada di database,
                                        misalnya transaksi uji dari dashboard Midtrans atau data dari server lain.
                                    </p>
                                @endif
                            </div>
                        </div>

                        @if ($related->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Callback Lain untuk VA Ini</h4>
                                </div>
                                <div class="card-body">
                                    @foreach ($related as $item)
                                        <div class="mb-2">
                                            <a
                                                href="{{ route('superadmin.va-logs.show', $item->id) }}">{{ optional($item->received_at)->format('d M Y H:i:s') }}</a>
                                            <span
                                                class="badge badge-{{ $item->is_success ? 'success' : 'danger' }}">{{ $item->request_payload['transaction_status'] ?? $item->event_type }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Data Diterima (request)</h4>
                                <div class="card-header-action">
                                    <button type="button" class="btn btn-sm btn-light js-copy"
                                        data-target="#req-payload"><i class="fas fa-copy"></i> Salin</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <pre id="req-payload" class="bg-light p-3 mb-0" style="max-height:480px;overflow:auto;font-size:12px">{{ $json($log->request_payload) }}</pre>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h4>Balasan Server (response)</h4>
                            </div>
                            <div class="card-body">
                                <pre class="bg-light p-3 mb-0" style="max-height:240px;overflow:auto;font-size:12px">{{ $json($log->response_payload) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        $('.js-copy').on('click', function() {
            navigator.clipboard?.writeText($($(this).data('target')).text())
                .then(() => $(this).html('<i class="fas fa-check"></i> Tersalin'));
        });
    </script>
@endpush
