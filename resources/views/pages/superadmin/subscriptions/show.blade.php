@extends('layouts.app')

@section('title', 'Langganan ' . ($subscription->company->name ?? ''))

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $badge = ['trial' => 'info', 'active' => 'success', 'grace' => 'warning', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $invLabel = ['paid' => 'Lunas', 'pending' => 'Menunggu', 'expired' => 'Kedaluwarsa', 'cancelled' => 'Dibatalkan'];
        $invBadge = ['paid' => 'success', 'pending' => 'warning', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $company = $subscription->company;
        $isOpen = $subscription->isActive();
        $days = $subscription->daysRemaining();
        $lockAt = $subscription->expires_at?->copy()->addDays($graceDays);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.subscriptions.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Langganan</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.subscriptions.edit', $subscription->id) }}" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Manual</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.subscriptions.index') }}">Langganan</a></div>
                    <div class="breadcrumb-item">{{ $company->name ?? '-' }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                {{-- Status akses --}}
                <div class="alert {{ $isOpen ? ($subscription->status === 'grace' ? 'alert-warning' : 'alert-success') : 'alert-danger' }}">
                    @if ($subscription->status === 'grace')
                        <i class="fas fa-exclamation-triangle"></i> <strong>Masa toleransi.</strong>
                        Aplikasi tenant masih terbuka, tapi akan <strong>terkunci {{ optional($lockAt)->format('d M Y H:i') }}</strong>
                        ({{ $days }} hari lagi) kalau belum dibayar.
                    @elseif ($isOpen)
                        <i class="fas fa-unlock"></i> <strong>Akses terbuka.</strong>
                        Berlaku sampai {{ optional($subscription->expires_at)->format('d M Y H:i') }} (sisa {{ $days }} hari),
                        setelah itu ada toleransi {{ $graceDays }} hari.
                    @else
                        <i class="fas fa-lock"></i> <strong>Akses terkunci.</strong>
                        Semua pengguna tenant ini tidak bisa memakai aplikasi. Hanya pengelola yang bisa membuka menu langganan untuk membayar.
                    @endif
                </div>

                <div class="row">
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Ringkasan</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:120px">Tenant</th>
                                        <td>
                                            @if ($company)
                                                <a href="{{ route('superadmin.tenants.show', $company->id) }}">{{ $company->name }}</a>
                                                <div class="text-small text-muted">{{ $typeLabels[$company->type] ?? ucfirst($company->type) }}</div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Paket</th>
                                        <td>
                                            @if ($subscription->plan)
                                                <a href="{{ route('superadmin.plans.show', $subscription->plan_id) }}">{{ $subscription->plan->name }}</a>
                                                <div class="text-small text-muted">{{ $subscription->plan->is_free ? 'Gratis' : $rupiah($subscription->plan->price) }} / {{ $subscription->plan->duration_days }} hari</div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td><span class="badge badge-{{ $badge[$subscription->status] ?? 'secondary' }}">{{ $statuses[$subscription->status] ?? $subscription->status }}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Mulai</th>
                                        <td>{{ optional($subscription->started_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Berakhir</th>
                                        <td>{{ optional($subscription->expires_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Pernah trial</th>
                                        <td>{{ $subscription->has_used_trial ? 'Ya' : 'Belum' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Invoice terakhir</th>
                                        <td>
                                            @if ($subscription->lastInvoice)
                                                <a href="{{ route('superadmin.invoices.show', $subscription->last_invoice_id) }}">{{ $subscription->lastInvoice->invoice_number }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Total dibayar</th>
                                        <td><strong>{{ $rupiah($paidTotal) }}</strong></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer bg-whitesmoke">
                                <button type="button" class="btn btn-success btn-block" data-toggle="modal" data-target="#extendModal">
                                    <i class="fas fa-calendar-plus"></i> Perpanjang
                                </button>
                                <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#planModal">
                                    <i class="fas fa-exchange-alt"></i> Ganti Paket
                                </button>
                                <a href="{{ route('superadmin.subscriptions.edit', $subscription->id) }}" class="btn btn-outline-primary btn-block">
                                    <i class="fas fa-edit"></i> Atur Tanggal &amp; Status Manual
                                </a>
                                @if (in_array($subscription->status, ['cancelled', 'expired']) && $subscription->expires_at?->isFuture())
                                    <form action="{{ route('superadmin.subscriptions.reactivate', $subscription->id) }}" method="POST">
                                        @csrf
                                        <button class="btn btn-outline-success btn-block"><i class="fas fa-undo"></i> Aktifkan Kembali</button>
                                    </form>
                                @endif
                                <form action="{{ route('superadmin.subscriptions.sync', $subscription->id) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-light btn-block"><i class="fas fa-sync-alt"></i> Sinkronkan Status</button>
                                </form>
                                @if ($subscription->status !== 'cancelled')
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal" data-target="#cancelModal">
                                        <i class="fas fa-ban"></i> Batalkan Langganan
                                    </button>
                                @endif
                                @if ($canDelete)
                                    <button type="button" class="btn btn-link text-danger btn-block" data-toggle="modal" data-target="#deleteModal">
                                        <i class="fas fa-trash"></i> Hapus langganan
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Invoice</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>No. Invoice</th>
                                                <th>Paket</th>
                                                <th class="text-right">Total</th>
                                                <th>Status</th>
                                                <th>Tanggal</th>
                                                <th>VA</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($invoices as $inv)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('superadmin.invoices.show', $inv->id) }}">{{ $inv->invoice_number }}</a>
                                                        @if ($inv->discount)
                                                            <div class="text-small text-muted"><i class="fas fa-tag"></i> {{ $inv->discount->code ?? $inv->discount->name }}</div>
                                                        @endif
                                                    </td>
                                                    <td>{{ $inv->plan->name ?? '-' }}</td>
                                                    <td class="text-right">{{ $rupiah($inv->total_amount) }}</td>
                                                    <td><span class="badge badge-{{ $invBadge[$inv->status] ?? 'secondary' }}">{{ $invLabel[$inv->status] ?? ucfirst($inv->status) }}</span></td>
                                                    <td class="text-small">
                                                        Terbit {{ optional($inv->issued_at)->format('d M Y') }}
                                                        @if ($inv->paid_at)
                                                            <br>Lunas {{ \Carbon\Carbon::parse($inv->paid_at)->format('d M Y') }}
                                                        @elseif ($inv->status === 'pending')
                                                            <br>Jatuh tempo {{ optional($inv->due_at)->format('d M Y H:i') }}
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($inv->vaPayment)
                                                            <a href="{{ route('superadmin.va-payments.show', $inv->vaPayment->id) }}">{{ strtoupper($inv->vaPayment->bank) }}</a>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada invoice</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @if ($invoices->hasPages())
                                <div class="card-footer text-right">{{ $invoices->links() }}</div>
                            @endif
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Perubahan Manual</h4>
                            </div>
                            <div class="card-body">
                                @forelse ($history as $log)
                                    <div class="d-flex mb-3">
                                        <div class="mr-3 text-primary"><i class="fas fa-circle" style="font-size:8px"></i></div>
                                        <div>
                                            <div class="small text-muted">
                                                {{ $log->created_at->format('d M Y H:i') }} · {{ $log->user->name ?? 'Sistem' }} · <code>{{ $log->action }}</code>
                                            </div>
                                            <div>{{ $log->description }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Belum ada perubahan manual.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- ── MODAL PERPANJANG ───────────────────────────────── --}}
    <div class="modal fade" id="extendModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.subscriptions.extend', $subscription->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Perpanjang Langganan</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            Dihitung dari {{ $subscription->expires_at?->isFuture() ? 'tanggal berakhir sekarang (' . $subscription->expires_at->format('d M Y') . ')' : 'hari ini, karena masa aktif sudah lewat' }}.
                            Tidak membuat invoice.
                        </p>
                        <div class="form-group">
                            <label>Jumlah hari <span class="text-danger">*</span></label>
                            <input type="number" name="extend_days" id="extend_days" class="form-control" min="1" max="730" value="{{ old('extend_days', 7) }}" required>
                            <div class="mt-2">
                                @foreach ([3, 7, 14, 30, 90, 365] as $d)
                                    <button type="button" class="btn btn-sm btn-light mb-1 js-days" data-days="{{ $d }}">+{{ $d }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="500" required placeholder="Contoh: Kompensasi server down 2 hari">{{ old('reason') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Perpanjang</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── MODAL GANTI PAKET ──────────────────────────────── --}}
    <div class="modal fade" id="planModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.subscriptions.change-plan', $subscription->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ganti Paket</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Tanpa pembayaran dan tanpa invoice.</p>
                        <div class="form-group">
                            <label>Paket baru <span class="text-danger">*</span></label>
                            <select name="plan_id" class="form-control" required>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected($plan->id === $subscription->plan_id)>
                                        {{ $plan->name }} — {{ $plan->is_free ? 'Gratis' : $rupiah($plan->price) }} / {{ $plan->duration_days }} hari
                                        {{ $plan->is_active ? '' : '(nonaktif)' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="reset_period" value="1" class="custom-control-input" id="reset_period">
                                <label class="custom-control-label" for="reset_period">Mulai ulang masa aktif dari hari ini sesuai durasi paket baru</label>
                            </div>
                            <small class="text-muted">Jika tidak dicentang, tanggal berakhir tetap.</small>
                        </div>
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Ganti Paket</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── MODAL BATALKAN ─────────────────────────────────── --}}
    <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.subscriptions.cancel', $subscription->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Batalkan Langganan</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small">Akses aplikasi seluruh pengguna <strong>{{ $company->name ?? '' }}</strong> langsung terkunci (tanpa masa toleransi).</p>
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Batalkan Langganan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── MODAL HAPUS ────────────────────────────────────── --}}
    @if ($canDelete)
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('superadmin.subscriptions.destroy', $subscription->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title text-danger">Hapus Langganan</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="small">
                                Baris langganan dihapus, sehingga tenant berstatus <b>belum berlangganan</b> dan aplikasinya terkunci.
                                Biasanya dipakai untuk membereskan langganan yang salah dibuat.
                            </p>
                            <div class="form-group mb-0">
                                <label>Alasan <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
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
    @endif
@endsection

@push('scripts')
    <script>
        $(function () {
            // Stisla: modal dipindah ke <body> supaya tidak tertutup backdrop
            $('#extendModal, #planModal, #cancelModal, #deleteModal').appendTo('body');

            $('.js-days').on('click', function () {
                $('#extend_days').val($(this).data('days'));
            });
        });
    </script>
@endpush
