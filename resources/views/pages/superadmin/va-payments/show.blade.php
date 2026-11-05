@extends('layouts.app')

@section('title', 'VA ' . $vaPayment->va_number)

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $badge = ['pending' => 'warning', 'paid' => 'success', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $invoice = $vaPayment->invoice;
        $json = fn($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.va-payments.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail VA</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.va-payments.index') }}">VA Payment</a></div>
                    <div class="breadcrumb-item">{{ $vaPayment->va_number }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($isStale)
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i> Masa berlaku VA sudah lewat, tapi statusnya masih menunggu bayar.
                        Klik <b>Cek Status di Midtrans</b> untuk menyamakan.
                    </div>
                @endif
                @if ($invoice && $invoice->status !== $vaPayment->status)
                    <div class="alert alert-light border">
                        <i class="fas fa-exclamation-circle text-warning"></i>
                        Status VA (<b>{{ $statuses[$vaPayment->status] ?? $vaPayment->status }}</b>) berbeda dengan status invoice
                        (<b>{{ $statuses[$invoice->status] ?? $invoice->status }}</b>).
                    </div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ strtoupper($vaPayment->bank) }} Virtual Account</h4>
                                <div class="card-header-action">
                                    <span class="badge badge-{{ $badge[$vaPayment->status] ?? 'secondary' }}">{{ $statuses[$vaPayment->status] ?? ucfirst($vaPayment->status) }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="text-muted small">Nomor VA</div>
                                    <div class="h4 mb-1"><code id="va-number">{{ $vaPayment->va_number }}</code>
                                        <button type="button" class="btn btn-sm btn-light" id="copy-va" title="Salin"><i class="fas fa-copy"></i></button>
                                    </div>
                                    <div class="h5 text-primary mb-0">{{ $rupiah($vaPayment->amount) }}</div>
                                </div>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:140px">Atas nama</th>
                                        <td>{{ $vaPayment->va_name }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tenant</th>
                                        <td>
                                            @if ($vaPayment->company)
                                                <a href="{{ route('superadmin.tenants.show', $vaPayment->company_id) }}">{{ $vaPayment->company->name }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Invoice</th>
                                        <td>
                                            @if ($invoice)
                                                <a href="{{ route('superadmin.invoices.show', $invoice->id) }}">{{ $invoice->invoice_number }}</a>
                                                <span class="badge badge-{{ $badge[$invoice->status] ?? 'secondary' }}">{{ $statuses[$invoice->status] ?? $invoice->status }}</span>
                                                <div class="text-small text-muted">{{ $invoice->plan->name ?? '-' }}</div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td>{{ optional($vaPayment->created_va_at ?? $vaPayment->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Berlaku s/d</th>
                                        <td class="{{ $isStale ? 'text-danger' : '' }}">{{ optional($vaPayment->expired_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibayar</th>
                                        <td>{{ $vaPayment->paid_at ? $vaPayment->paid_at->format('d M Y H:i') : '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>ID transaksi</th>
                                        <td class="text-small">{{ $vaPayment->bank_reference_no ?: '-' }}</td>
                                    </tr>
                                    @if ($vaPayment->payment_request_id)
                                        <tr>
                                            <th>Biller code</th>
                                            <td class="text-small">{{ $vaPayment->payment_request_id }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                            <div class="card-footer bg-whitesmoke">
                                @if (!$vaPayment->isPaid() && $invoice && !$invoice->isPaid())
                                    <form action="{{ route('superadmin.va-payments.check-status', $vaPayment->id) }}" method="POST">
                                        @csrf
                                        <button class="btn btn-primary btn-block"><i class="fas fa-sync-alt"></i> Cek Status di Midtrans</button>
                                    </form>
                                @endif
                                @if (!$vaPayment->isPaid())
                                    <button type="button" class="btn btn-success btn-block" data-toggle="modal" data-target="#paidModal">
                                        <i class="fas fa-check"></i> Tandai Lunas
                                    </button>
                                @endif
                                @if ($vaPayment->isPending())
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal" data-target="#closeModal">
                                        <i class="fas fa-ban"></i> Tutup VA
                                    </button>
                                @endif
                                @if ($invoice)
                                    <a href="{{ route('superadmin.invoices.show', $invoice->id) }}" class="btn btn-light btn-block"><i class="fas fa-file-invoice"></i> Buka Invoice</a>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Tindakan Admin</h4>
                            </div>
                            <div class="card-body">
                                @forelse ($history as $log)
                                    <div class="mb-2">
                                        <div class="text-small text-muted">{{ $log->created_at->format('d M Y H:i') }} · {{ $log->user->name ?? 'Sistem' }}</div>
                                        <div>{{ $log->description }}</div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Belum ada tindakan manual.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h4>Callback dari Midtrans ({{ $logs->count() }})</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Waktu</th>
                                                <th>Event</th>
                                                <th>Berhasil</th>
                                                <th>HTTP</th>
                                                <th>Data</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($logs as $log)
                                                <tr>
                                                    <td class="text-small">{{ optional($log->received_at)->format('d M Y H:i:s') }}</td>
                                                    <td>
                                                        <code>{{ $log->event_type }}</code>
                                                        @if (!empty($log->request_payload['transaction_status']))
                                                            <div class="text-small text-muted">{{ $log->request_payload['transaction_status'] }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-{{ $log->is_success ? 'success' : 'danger' }}">{{ $log->is_success ? 'Ya' : 'Tidak' }}</span>
                                                        @if ($log->error_message)
                                                            <div class="text-small text-danger">{{ $log->error_message }}</div>
                                                        @endif
                                                    </td>
                                                    <td>{{ $log->response_http_code ?? '-' }}</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-light js-payload" data-payload="{{ $json($log->request_payload) }}" data-title="Callback {{ optional($log->received_at)->format('d M Y H:i:s') }}">
                                                            <i class="fas fa-code"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada callback dari Midtrans</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        @if ($bankResponse)
                            <div class="card">
                                <div class="card-header">
                                    <h4>Respons Midtrans Saat VA Dibuat</h4>
                                </div>
                                <div class="card-body">
                                    <pre class="bg-light p-3 mb-0" style="max-height:320px;overflow:auto;font-size:12px">{{ is_string($bankResponse) ? $bankResponse : $json($bankResponse) }}</pre>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- ── MODAL TANDAI LUNAS ─────────────────────────────── --}}
    @if (!$vaPayment->isPaid())
        <div class="modal fade" id="paidModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('superadmin.va-payments.mark-paid', $vaPayment->id) }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Tandai VA Lunas</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light border small">
                                Gunakan hanya jika dana <b>sudah masuk</b> (cek dashboard Midtrans / mutasi), tapi notifikasinya tidak sampai.
                                @if ($invoice && !$invoice->isPaid())
                                    Invoice ikut lunas dan langganan diperpanjang {{ $invoice->plan->duration_days ?? '-' }} hari.
                                @elseif ($invoice)
                                    Invoice sudah lunas, jadi langganan <b>tidak</b> diperpanjang lagi.
                                @endif
                            </div>
                            <div class="form-group">
                                <label>ID transaksi / referensi</label>
                                <input type="text" name="reference" class="form-control" maxlength="100" placeholder="dari dashboard Midtrans">
                            </div>
                            <div class="form-group mb-0">
                                <label>Alasan <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success">Tandai Lunas</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── MODAL TUTUP VA ─────────────────────────────────── --}}
    @if ($vaPayment->isPending())
        <div class="modal fade" id="closeModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('superadmin.va-payments.close', $vaPayment->id) }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title text-danger">Tutup VA</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">VA ditutup di Midtrans sehingga tidak bisa dibayar lagi.</p>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="cancel_invoice" value="1" class="custom-control-input" id="cancel_invoice">
                                    <label class="custom-control-label" for="cancel_invoice">Batalkan invoice-nya juga</label>
                                </div>
                                <small class="text-muted">Jika tidak dicentang, invoice tetap menunggu bayar dan bisa dibuatkan VA baru.</small>
                            </div>
                            <div class="form-group mb-0">
                                <label>Alasan <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">Tutup VA</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── MODAL DATA CALLBACK ────────────────────────────── --}}
    <div class="modal fade" id="payloadModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="payloadTitle">Data</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <pre class="bg-light p-3 mb-0" id="payloadBody" style="max-height:60vh;overflow:auto;font-size:12px"></pre>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('#paidModal, #closeModal, #payloadModal').appendTo('body');

            $('.js-payload').on('click', function () {
                $('#payloadTitle').text($(this).data('title'));
                $('#payloadBody').text($(this).attr('data-payload'));
                $('#payloadModal').modal('show');
            });

            $('#copy-va').on('click', function () {
                navigator.clipboard?.writeText($('#va-number').text().trim())
                    .then(() => $(this).html('<i class="fas fa-check"></i>'));
            });
        });
    </script>
@endpush
