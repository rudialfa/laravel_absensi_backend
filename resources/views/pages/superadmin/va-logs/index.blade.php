@extends('layouts.app')

@section('title', 'Log Callback Bank')

@section('main')
    @php
        $hasFilter = collect(request()->only(['search', 'bank', 'event_type', 'result', 'from', 'to', 'va_payment_id']))
            ->filter()
            ->isNotEmpty();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Log Callback Bank</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Log Callback</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <h2 class="section-title">Notifikasi Masuk dari Midtrans &amp; Bank</h2>
                <p class="section-lead">
                    Setiap notifikasi pembayaran yang diterima server dicatat di sini, berhasil maupun gagal.
                    Log tidak bisa diubah karena berfungsi sebagai bukti.
                </p>

                <div class="row">
                    <div class="col-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-primary"><i class="fas fa-exchange-alt"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>24 Jam Terakhir</h4>
                                </div>
                                <div class="card-body">{{ number_format($stats['today']) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <a href="{{ route('superadmin.va-logs.index', ['result' => 'failed']) }}"
                            class="text-decoration-none">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-danger"><i class="fas fa-times-circle"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Gagal (24 jam)</h4>
                                    </div>
                                    <div class="card-body">{{ number_format($stats['failed24']) }}</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-lg-3">
                        <a href="{{ route('superadmin.va-logs.index', ['result' => 'unmatched']) }}"
                            class="text-decoration-none">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-warning"><i class="fas fa-question-circle"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>VA Tidak Ditemukan</h4>
                                    </div>
                                    <div class="card-body">{{ number_format($stats['unmatched']) }}</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-secondary"><i class="fas fa-database"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Total Log</h4>
                                </div>
                                <div class="card-body">
                                    {{ number_format($stats['total']) }}
                                    @if ($stats['oldest'])
                                        <div class="text-small text-muted font-weight-normal">sejak
                                            {{ \Carbon\Carbon::parse($stats['oldest'])->format('d M Y') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.va-logs.index') }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control" placeholder="No. invoice, transaction_id, no. VA, IP">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="bank" class="form-control">
                                        <option value="">Semua Bank</option>
                                        @foreach ($banks as $bank)
                                            <option value="{{ $bank }}" @selected(request('bank') === $bank)>
                                                {{ strtoupper($bank) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="result" class="form-control">
                                        <option value="">Semua Hasil</option>
                                        <option value="success" @selected(request('result') === 'success')>Berhasil</option>
                                        <option value="failed" @selected(request('result') === 'failed')>Gagal</option>
                                        <option value="unmatched" @selected(request('result') === 'unmatched')>VA tidak ditemukan</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <input type="date" name="from" value="{{ request('from') }}" class="form-control"
                                        title="Dari tanggal">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <input type="date" name="to" value="{{ request('to') }}" class="form-control"
                                        title="Sampai tanggal">
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block" title="Cari"><i
                                            class="fas fa-search"></i></button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    @if ($hasFilter)
                                        <a href="{{ route('superadmin.va-logs.index') }}" class="text-small"><i
                                                class="fas fa-times"></i> Hapus filter</a>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal"
                                    data-target="#pruneModal">
                                    <i class="fas fa-broom"></i> Bersihkan Log Lama
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Bank / Event</th>
                                        <th>Invoice / VA</th>
                                        <th>Status Transaksi</th>
                                        <th>Hasil</th>
                                        <th class="text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($logs as $log)
                                        @php
                                            $payload = is_array($log->request_payload) ? $log->request_payload : [];
                                            $orderId = $payload['order_id'] ?? null;
                                        @endphp
                                        <tr>
                                            <td class="text-small">
                                                {{ optional($log->received_at)->format('d M Y') }}<br>
                                                <strong>{{ optional($log->received_at)->format('H:i:s') }}</strong>
                                            </td>
                                            <td>
                                                {{ strtoupper($log->bank) }}
                                                <div class="text-small text-muted">
                                                    {{ $events[$log->event_type] ?? $log->event_type }}</div>
                                            </td>
                                            <td>
                                                @if ($log->vaPayment)
                                                    @if ($log->vaPayment->invoice)
                                                        <a
                                                            href="{{ route('superadmin.invoices.show', $log->vaPayment->invoice_id) }}">{{ $log->vaPayment->invoice->invoice_number }}</a>
                                                    @endif
                                                    <div class="text-small">
                                                        <a
                                                            href="{{ route('superadmin.va-payments.show', $log->va_payment_id) }}"><code>{{ $log->vaPayment->va_number }}</code></a>
                                                        · {{ $log->vaPayment->company->name ?? '-' }}
                                                    </div>
                                                @else
                                                    <span class="text-warning"><i class="fas fa-question-circle"></i>
                                                        Tidak cocok</span>
                                                    @if ($orderId)
                                                        <div class="text-small text-muted">order_id: {{ $orderId }}
                                                        </div>
                                                    @endif
                                                @endif
                                            </td>
                                            <td>
                                                @if (!empty($payload['transaction_status']))
                                                    <code>{{ $payload['transaction_status'] }}</code>
                                                @else
                                                    -
                                                @endif
                                                @if (!empty($payload['gross_amount']))
                                                    <div class="text-small text-muted">Rp
                                                        {{ number_format((float) $payload['gross_amount'], 0, ',', '.') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $log->is_success ? 'success' : 'danger' }}">
                                                    {{ $log->is_success ? 'Berhasil' : 'Gagal' }} ·
                                                    {{ $log->response_http_code ?? '-' }}
                                                </span>
                                                @if ($log->error_message)
                                                    <div class="text-small text-danger">
                                                        {{ \Illuminate\Support\Str::limit($log->error_message, 60) }}</div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.va-logs.show', $log->id) }}"
                                                    class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Belum ada log</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($logs->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">Menampilkan {{ $logs->firstItem() }}–{{ $logs->lastItem() }} dari
                                {{ $logs->total() }}</small>
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    {{-- Modal bersihkan log --}}
    <div class="modal fade" id="pruneModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.va-logs.prune') }}" method="POST"
                onsubmit="return confirm('Hapus permanen log lama? Tindakan ini tidak bisa dibatalkan.')">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Bersihkan Log Lama</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            Untuk menghemat database. Log yang umurnya kurang dari {{ $minKeepDays }} hari selalu
                            disimpan.
                            Status VA dan invoice tidak terpengaruh.
                        </p>
                        <div class="form-group">
                            <label>Hapus log yang lebih lama dari</label>
                            <select name="older_than_days" class="form-control">
                                @foreach ([90 => '3 bulan', 180 => '6 bulan', 365 => '1 tahun', 730 => '2 tahun'] as $days => $label)
                                    <option value="{{ $days }}" @selected($days === 365)>{{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="hidden" name="only_success" value="0">
                            <input type="checkbox" name="only_success" value="1" class="custom-control-input"
                                id="only_success" checked>
                            <label class="custom-control-label" for="only_success">Hanya yang berhasil (log gagal tetap
                                disimpan)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            $('#pruneModal').appendTo('body');
        });
    </script>
@endpush
