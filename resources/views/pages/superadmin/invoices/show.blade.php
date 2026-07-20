@extends('layouts.app')

@section('title', 'Detail Invoice')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Detail Invoice</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.invoices.index') }}">Invoices</a></div>
                    <div class="breadcrumb-item">{{ $invoice->invoice_number }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-7">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $invoice->invoice_number }}</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr>
                                        <th style="width:200px">Tenant</th>
                                        <td>
                                            <a href="{{ route('superadmin.tenants.show', $invoice->company_id) }}">
                                                {{ $invoice->company->name ?? '-' }}
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Paket</th>
                                        <td>{{ $invoice->plan->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Subtotal</th>
                                        <td>Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Diskon</th>
                                        <td>
                                            Rp {{ number_format($invoice->discount_amount, 0, ',', '.') }}
                                            @if ($invoice->discount)
                                                ({{ $invoice->discount->name }})
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Total Dibayar</th>
                                        <td><strong>Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span
                                                class="badge badge-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'pending' ? 'warning' : 'secondary') }}">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Diterbitkan</th>
                                        <td>{{ optional($invoice->issued_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jatuh Tempo</th>
                                        <td>{{ optional($invoice->due_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibayar Pada</th>
                                        <td>{{ $invoice->paid_at ? $invoice->paid_at->format('d M Y H:i') : '-' }}</td>
                                    </tr>
                                    @if ($invoice->notes)
                                        <tr>
                                            <th>Catatan</th>
                                            <td>{{ $invoice->notes }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                            @if ($invoice->isPending())
                                <div class="card-footer text-right">
                                    <button type="button" class="btn btn-danger" data-toggle="modal"
                                        data-target="#rejectModal">
                                        <i class="fas fa-times"></i> Tolak
                                    </button>
                                    <form action="{{ route('superadmin.invoices.verify', $invoice->id) }}" method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Verifikasi invoice ini sebagai lunas dan aktifkan langganan tenant?');">
                                        @csrf
                                        <button class="btn btn-success"><i class="fas fa-check"></i> Verifikasi
                                            Pembayaran</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-12 col-md-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>Virtual Account</h4>
                            </div>
                            <div class="card-body">
                                @if ($invoice->vaPayment)
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <th style="width:140px">Bank</th>
                                            <td>{{ strtoupper($invoice->vaPayment->bank) }}</td>
                                        </tr>
                                        <tr>
                                            <th>No. VA</th>
                                            <td>{{ $invoice->vaPayment->va_number }}</td>
                                        </tr>
                                        <tr>
                                            <th>Atas Nama</th>
                                            <td>{{ $invoice->vaPayment->va_name }}</td>
                                        </tr>
                                        <tr>
                                            <th>Status VA</th>
                                            <td>
                                                <span
                                                    class="badge badge-{{ $invoice->vaPayment->status === 'paid' ? 'success' : 'warning' }}">
                                                    {{ ucfirst($invoice->vaPayment->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Kedaluwarsa</th>
                                            <td>{{ optional($invoice->vaPayment->expired_at)->format('d M Y H:i') }}</td>
                                        </tr>
                                    </table>
                                @else
                                    <p class="text-muted mb-0">Belum ada VA yang dibuat untuk invoice ini.</p>
                                @endif
                            </div>
                        </div>

                        @if ($invoice->vaPayment && $invoice->vaPayment->logs->count())
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h4>Log Callback Bank</h4>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        @foreach ($invoice->vaPayment->logs as $log)
                                            <li class="mb-2">
                                                <span
                                                    class="badge badge-{{ $log->is_success ? 'success' : 'danger' }}">{{ $log->event_type }}</span>
                                                {{ optional($log->received_at)->format('d M Y H:i') }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.invoices.reject', $invoice->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tolak Invoice</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Catatan (opsional)</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Alasan penolakan"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak Invoice</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
