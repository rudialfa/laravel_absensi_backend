@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_number)

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $badge = ['pending' => 'warning', 'paid' => 'success', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $va = $invoice->vaPayment;
        $canVerify = in_array($invoice->status, ['pending', 'expired']);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.invoices.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Invoice</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.invoices.print', $invoice->id) }}" target="_blank" class="btn btn-light"><i class="fas fa-print"></i> Cetak</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.invoices.index') }}">Invoice</a></div>
                    <div class="breadcrumb-item">{{ $invoice->invoice_number }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($isOverdue)
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i> Invoice ini sudah lewat jatuh tempo ({{ $invoice->due_at->format('d M Y H:i') }}) tapi belum dibayar.
                    </div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $invoice->invoice_number }}</h4>
                                <div class="card-header-action">
                                    <span class="badge badge-{{ $badge[$invoice->status] ?? 'secondary' }}">{{ $statuses[$invoice->status] ?? ucfirst($invoice->status) }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-3">
                                    <tr>
                                        <th style="width:160px">Tenant</th>
                                        <td>
                                            @if ($invoice->company)
                                                <a href="{{ route('superadmin.tenants.show', $invoice->company_id) }}">{{ $invoice->company->name }}</a>
                                                <div class="text-small text-muted">{{ $invoice->company->email }}</div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Langganan</th>
                                        <td>
                                            @if ($invoice->subscription)
                                                <a href="{{ route('superadmin.subscriptions.show', $invoice->subscription_id) }}">
                                                    Lihat langganan
                                                </a>
                                                <span class="text-muted text-small">· berakhir {{ optional($invoice->subscription->expires_at)->format('d M Y') }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Diterbitkan</th>
                                        <td>{{ optional($invoice->issued_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jatuh tempo</th>
                                        <td>{{ optional($invoice->due_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibayar</th>
                                        <td>{{ $invoice->paid_at ? $invoice->paid_at->format('d M Y H:i') : '-' }}</td>
                                    </tr>
                                </table>

                                <table class="table table-sm border mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Rincian</th>
                                            <th class="text-right">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                Langganan
                                                @if ($invoice->plan)
                                                    <a href="{{ route('superadmin.plans.show', $invoice->plan_id) }}">{{ $invoice->plan->name }}</a>
                                                    ({{ $invoice->plan->duration_days }} hari)
                                                @endif
                                            </td>
                                            <td class="text-right">{{ $rupiah($invoice->subtotal) }}</td>
                                        </tr>
                                        @if ((float) $invoice->discount_amount > 0)
                                            <tr>
                                                <td>
                                                    Diskon
                                                    @if ($invoice->discount)
                                                        <a href="{{ route('superadmin.discounts.show', $invoice->discount_id) }}">{{ $invoice->discount->code ?? $invoice->discount->name }}</a>
                                                    @endif
                                                </td>
                                                <td class="text-right text-danger">−{{ $rupiah($invoice->discount_amount) }}</td>
                                            </tr>
                                        @endif
                                        <tr class="font-weight-bold">
                                            <td>Total</td>
                                            <td class="text-right">{{ $rupiah($invoice->total_amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                @if ($invoice->notes)
                                    <div class="mt-3">
                                        <h6 class="text-muted small mb-1">Catatan</h6>
                                        <div class="bg-light rounded p-2 small">{!! nl2br(e($invoice->notes)) !!}</div>
                                    </div>
                                @endif
                            </div>
                            <div class="card-footer bg-whitesmoke d-flex flex-wrap justify-content-end" style="gap:6px">
                                <a href="{{ route('superadmin.invoices.edit', $invoice->id) }}" class="btn btn-outline-primary"><i class="fas fa-edit"></i> Edit Catatan</a>
                                @if ($invoice->status === 'pending')
                                    @if ($isOverdue)
                                        <form action="{{ route('superadmin.invoices.expire', $invoice->id) }}" method="POST">
                                            @csrf
                                            <button class="btn btn-outline-secondary"><i class="fas fa-hourglass-end"></i> Tandai Kedaluwarsa</button>
                                        </form>
                                    @endif
                                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectModal"><i class="fas fa-times"></i> Batalkan</button>
                                @endif
                                @if ($canVerify)
                                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#verifyModal"><i class="fas fa-check"></i> Verifikasi Lunas</button>
                                @endif
                                @if ($canDelete)
                                    <button type="button" class="btn btn-link text-danger" data-toggle="modal" data-target="#deleteModal"><i class="fas fa-trash"></i> Hapus</button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-5">
                        {{-- VA --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Virtual Account</h4>
                            </div>
                            <div class="card-body">
                                @if ($va)
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th style="width:110px">Bank</th>
                                            <td>{{ strtoupper($va->bank) }}</td>
                                        </tr>
                                        <tr>
                                            <th>No. VA</th>
                                            <td><code>{{ $va->va_number }}</code></td>
                                        </tr>
                                        <tr>
                                            <th>Atas nama</th>
                                            <td>{{ $va->va_name }}</td>
                                        </tr>
                                        <tr>
                                            <th>Nominal</th>
                                            <td>{{ $rupiah($va->amount) }}</td>
                                        </tr>
                                        <tr>
                                            <th>Status VA</th>
                                            <td><span class="badge badge-{{ $badge[$va->status] ?? 'secondary' }}">{{ $statuses[$va->status] ?? ucfirst($va->status) }}</span></td>
                                        </tr>
                                        <tr>
                                            <th>Berlaku s/d</th>
                                            <td>{{ optional($va->expired_at)->format('d M Y H:i') }}</td>
                                        </tr>
                                        @if ($va->bank_reference_no)
                                            <tr>
                                                <th>Ref.</th>
                                                <td class="text-small">{{ $va->bank_reference_no }}</td>
                                            </tr>
                                        @endif
                                    </table>
                                @else
                                    <p class="text-muted mb-0">Belum ada VA untuk invoice ini.</p>
                                @endif
                            </div>
                            <div class="card-footer bg-whitesmoke">
                                @if ($va)
                                    <a href="{{ route('superadmin.va-payments.show', $va->id) }}" class="btn btn-sm btn-light"><i class="fas fa-external-link-alt"></i> Detail VA</a>
                                    @if (!$invoice->isPaid() && !$va->isPaid())
                                        <form action="{{ route('superadmin.invoices.check-status', $invoice->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-primary"><i class="fas fa-sync-alt"></i> Cek Status di Midtrans</button>
                                        </form>
                                    @endif
                                @endif
                                @if ($invoice->status === 'pending' && (!$va || !$va->isPending()) && !$isOverdue)
                                    <form action="{{ route('superadmin.invoices.create-va', $invoice->id) }}" method="POST" class="form-inline mt-2">
                                        @csrf
                                        <select name="bank" class="form-control form-control-sm mr-2" required>
                                            @foreach ($banks as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-success"><i class="fas fa-plus"></i> Buat VA</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        {{-- Log bank --}}
                        @if ($va && $va->logs->count())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Callback Midtrans ({{ $va->logs->count() }})</h4>
                                </div>
                                <div class="card-body">
                                    @foreach ($va->logs as $log)
                                        <div class="mb-2">
                                            <span class="badge badge-{{ $log->is_success ? 'success' : 'danger' }}">{{ $log->event_type }}</span>
                                            <span class="text-small text-muted">{{ optional($log->received_at)->format('d M Y H:i:s') }} · HTTP {{ $log->response_http_code }}</span>
                                            @if ($log->error_message)
                                                <div class="text-small text-danger">{{ $log->error_message }}</div>
                                            @endif
                                            <details class="text-small">
                                                <summary class="text-primary" style="cursor:pointer">Lihat data</summary>
                                                <pre class="bg-light p-2 mb-0" style="max-height:220px;overflow:auto;font-size:11px">{{ json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </details>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Riwayat --}}
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
                </div>
            </div>
        </section>
    </div>

    {{-- ── MODAL VERIFIKASI ───────────────────────────────── --}}
    @if ($canVerify)
        <div class="modal fade" id="verifyModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('superadmin.invoices.verify', $invoice->id) }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Verifikasi Pembayaran</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light border small">
                                Invoice <b>{{ $invoice->invoice_number }}</b> ({{ $rupiah($invoice->total_amount) }}) ditandai lunas, lalu
                                langganan <b>{{ $invoice->company->name ?? '' }}</b> diperpanjang {{ $invoice->plan->duration_days ?? '-' }} hari
                                dan pengelola tenant mendapat notifikasi.
                            </div>
                            <div class="form-group">
                                <label>Metode pembayaran <span class="text-danger">*</span></label>
                                <select name="method" class="form-control" required>
                                    <option value="transfer">Transfer manual ke rekening</option>
                                    <option value="va">Lewat VA, tapi notifikasi bank tidak masuk</option>
                                    <option value="cash">Tunai</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>No. referensi / bukti</label>
                                <input type="text" name="reference" class="form-control" maxlength="100" placeholder="Contoh: no. transaksi bank">
                            </div>
                            <div class="form-group mb-0">
                                <label>Catatan <span class="text-danger">*</span></label>
                                <textarea name="note" class="form-control" rows="2" maxlength="500" required placeholder="Contoh: Bukti transfer dikirim via WA 3 Nov"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success">Verifikasi Lunas</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── MODAL BATALKAN ─────────────────────────────────── --}}
    @if ($invoice->status === 'pending')
        <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('superadmin.invoices.reject', $invoice->id) }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title text-danger">Batalkan Invoice</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">
                                VA (jika ada) ikut ditutup supaya tidak bisa dibayar. Kuota voucher yang dipakai dikembalikan.
                            </p>
                            <div class="form-group mb-0">
                                <label>Alasan <span class="text-danger">*</span></label>
                                <textarea name="notes" class="form-control" rows="3" maxlength="500" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Kembali</button>
                            <button type="submit" class="btn btn-danger">Batalkan Invoice</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── MODAL HAPUS ────────────────────────────────────── --}}
    @if ($canDelete)
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('superadmin.invoices.destroy', $invoice->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title text-danger">Hapus Invoice</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p>Ketik nomor invoice <strong>{{ $invoice->invoice_number }}</strong> untuk konfirmasi:</p>
                            <input type="text" name="confirm" class="form-control" autocomplete="off" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(function () {
            $('#verifyModal, #rejectModal, #deleteModal').appendTo('body');
        });
    </script>
@endpush
