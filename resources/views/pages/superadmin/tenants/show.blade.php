@extends('layouts.app')

@section('title', $tenant->name)

@section('main')
    @php
        $link = fn(string $route, ...$params) => \Illuminate\Support\Facades\Route::has($route) ? route($route, $params) : null;
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        $roleLabels = [
            'hr' => 'HR', 'employee' => 'Karyawan', 'admin' => 'Admin Sekolah', 'guru' => 'Guru',
            'wali' => 'Wali Murid', 'ustadz' => 'Ustadz', 'santri' => 'Santri', 'company' => 'Akun Company (lama)',
            'user' => 'User (lama)',
        ];
        $subBadge = ['trial' => 'info', 'active' => 'success', 'grace' => 'warning', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $subLabel = ['trial' => 'Trial', 'active' => 'Aktif', 'grace' => 'Masa toleransi', 'expired' => 'Kedaluwarsa', 'cancelled' => 'Dibatalkan'];
        $invBadge = ['paid' => 'success', 'pending' => 'warning', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $invLabel = ['paid' => 'Lunas', 'pending' => 'Menunggu', 'expired' => 'Kedaluwarsa', 'cancelled' => 'Dibatalkan'];
        $logo = $tenant->image_url
            ? (str_starts_with($tenant->image_url, 'http') ? $tenant->image_url : asset($tenant->image_url))
            : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Tenant</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.tenants.index') }}">Tenant</a></div>
                    <div class="breadcrumb-item">{{ $tenant->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @unless ($tenant->is_active)
                    <div class="alert alert-danger">
                        <strong><i class="fas fa-ban"></i> Tenant disuspend</strong>
                        @if ($tenant->suspended_at)
                            sejak {{ \Carbon\Carbon::parse($tenant->suspended_at)->format('d M Y H:i') }}
                        @endif
                        @if ($tenant->suspend_reason)
                            · Alasan: {{ $tenant->suspend_reason }}
                        @endif
                        <div class="small mt-1">Semua pengguna tenant ini tidak bisa login ke aplikasi.</div>
                    </div>
                @endunless

                <div class="row">
                    {{-- ── Profil lembaga ───────────────────────── --}}
                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    @if ($logo)
                                        <img src="{{ $logo }}" alt="" class="rounded border mr-3" style="width:48px;height:48px;object-fit:contain">
                                    @endif
                                    <div>
                                        <h4 class="mb-0">{{ $tenant->name }}</h4>
                                        <small class="text-muted">ID #{{ $tenant->id }} · terdaftar {{ optional($tenant->created_at)->format('d M Y') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:160px">Email</th>
                                        <td>{{ $tenant->email }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jenis</th>
                                        <td>
                                            {{ $typeLabels[$tenant->type] ?? ucfirst($tenant->type) }}
                                            @if ($tenant->is_boarding)
                                                <span class="badge badge-light">Berasrama</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Alamat</th>
                                        <td>{{ $tenant->address }}{{ $tenant->city ? ', ' . $tenant->city : '' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Titik Lokasi</th>
                                        <td>
                                            {{ $tenant->latitude }}, {{ $tenant->longitude }}
                                            <a href="https://www.google.com/maps?q={{ $tenant->latitude }},{{ $tenant->longitude }}"
                                                target="_blank" rel="noopener" class="ml-1">
                                                <i class="fas fa-map-marker-alt"></i> Buka peta
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Radius Absen</th>
                                        <td>{{ rtrim(rtrim(number_format((float) $tenant->radius_km, 2, '.', ''), '0'), '.') }} km
                                            ({{ number_format((float) $tenant->radius_km * 1000, 0, ',', '.') }} m)</td>
                                    </tr>
                                    <tr>
                                        <th>Jam Kerja</th>
                                        <td>{{ substr($tenant->time_in, 0, 5) }} – {{ substr($tenant->time_out, 0, 5) }}
                                            <span class="text-muted">({{ $tenant->timezone }})</span></td>
                                    </tr>
                                    <tr>
                                        <th>Hari Kerja</th>
                                        <td>{{ $workDaysLabel }}</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span class="badge badge-{{ $tenant->is_active ? 'success' : 'danger' }}">
                                                {{ $tenant->is_active ? 'Aktif' : 'Disuspend' }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer bg-whitesmoke text-right">
                                @if ($tenant->is_active)
                                    <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#suspendModal">
                                        <i class="fas fa-ban"></i> Suspend
                                    </button>
                                @else
                                    <form action="{{ route('superadmin.tenants.activate', $tenant->id) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Aktifkan kembali tenant {{ addslashes($tenant->name) }}?')">
                                        @csrf
                                        <button class="btn btn-success"><i class="fas fa-check"></i> Aktifkan Kembali</button>
                                    </form>
                                @endif
                                <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" class="btn btn-primary ml-1">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- ── Langganan ─────────────────────────────── --}}
                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>Langganan</h4>
                            </div>
                            <div class="card-body">
                                @if ($subscription)
                                    <div class="mb-3">
                                        <span class="badge badge-{{ $subBadge[$subscription->status] ?? 'secondary' }}">
                                            {{ $subLabel[$subscription->status] ?? ucfirst($subscription->status) }}
                                        </span>
                                        <strong class="ml-1">{{ $subscription->plan->name ?? '-' }}</strong>
                                    </div>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th style="width:120px">Mulai</th>
                                            <td>{{ optional($subscription->started_at)->format('d M Y') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Berakhir</th>
                                            <td>
                                                {{ optional($subscription->expires_at)->format('d M Y H:i') }}
                                                @if (in_array($subscription->status, ['trial', 'active', 'grace']))
                                                    <br><small class="text-muted">sisa {{ $subscription->daysRemaining() }} hari</small>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Pernah trial</th>
                                            <td>{{ $subscription->has_used_trial ? 'Ya' : 'Belum' }}</td>
                                        </tr>
                                    </table>
                                @else
                                    <p class="text-muted mb-0">Tenant ini belum pernah berlangganan (termasuk trial).</p>
                                @endif
                            </div>
                            @if ($subscription && ($url = $link('superadmin.subscriptions.show', $subscription->id)))
                                <div class="card-footer bg-whitesmoke text-right">
                                    <a href="{{ $url }}" class="btn btn-outline-primary">
                                        <i class="fas fa-sync-alt"></i> Kelola Langganan
                                    </a>
                                </div>
                            @endif
                        </div>

                        {{-- Pengguna per peran --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Pengguna ({{ $tenant->users_count }})</h4>
                                @if ($url = $link('superadmin.users.index'))
                                    <div class="card-header-action">
                                        <a href="{{ $url }}?company_id={{ $tenant->id }}" class="btn btn-sm btn-primary">Kelola</a>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    <tbody>
                                        @forelse ($usersByRole as $row)
                                            <tr>
                                                <td class="pl-4">{{ $roleLabels[$row->role] ?? ucfirst($row->role) }}</td>
                                                <td class="text-right pr-4">
                                                    <strong>{{ $row->total }}</strong>
                                                    @if ((int) $row->active !== (int) $row->total)
                                                        <small class="text-muted">({{ $row->active }} aktif)</small>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td class="text-center text-muted">Belum ada pengguna</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Akun pengelola ─────────────────────────── --}}
                <div class="card">
                    <div class="card-header">
                        <h4>Akun Pengelola ({{ $roleLabels[$adminRole] ?? ucfirst($adminRole) }})</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>No. HP</th>
                                        <th>Status</th>
                                        <th>Dibuat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($admins as $admin)
                                        <tr>
                                            <td>
                                                @if ($url = $link('superadmin.users.show', $admin->id))
                                                    <a href="{{ $url }}">{{ $admin->name }}</a>
                                                @else
                                                    {{ $admin->name }}
                                                @endif
                                            </td>
                                            <td>{{ $admin->email }}</td>
                                            <td>{{ $admin->phone ?: '-' }}</td>
                                            <td>
                                                <span class="badge badge-{{ $admin->is_active ? 'success' : 'secondary' }}">
                                                    {{ $admin->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </td>
                                            <td>{{ optional($admin->created_at)->format('d M Y') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                Tidak ada akun pengelola. Tenant ini tidak bisa mengurus pembayaran langganan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ── Riwayat invoice ───────────────────────── --}}
                <div class="card">
                    <div class="card-header">
                        <h4>Riwayat Invoice (10 terakhir)</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>No. Invoice</th>
                                        <th>Paket</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Diterbitkan</th>
                                        <th>Dibayar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tenant->invoices as $invoice)
                                        <tr>
                                            <td><a href="{{ route('superadmin.invoices.show', $invoice->id) }}">{{ $invoice->invoice_number }}</a></td>
                                            <td>{{ $invoice->plan->name ?? '-' }}</td>
                                            <td>{{ $rupiah($invoice->total_amount) }}</td>
                                            <td>
                                                <span class="badge badge-{{ $invBadge[$invoice->status] ?? 'secondary' }}">
                                                    {{ $invLabel[$invoice->status] ?? ucfirst($invoice->status) }}
                                                </span>
                                            </td>
                                            <td>{{ optional($invoice->issued_at)->format('d M Y') }}</td>
                                            <td>{{ $invoice->paid_at ? \Carbon\Carbon::parse($invoice->paid_at)->format('d M Y') : '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Belum ada invoice</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ── Zona berbahaya ────────────────────────── --}}
                <div class="card border border-danger">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                        <div class="mb-2 mb-md-0">
                            <h6 class="text-danger mb-1">Hapus Tenant</h6>
                            <span class="text-muted small">Menghapus tenant beserta SEMUA datanya secara permanen.
                                Gunakan Suspend jika hanya ingin menghentikan akses.</span>
                        </div>
                        <button type="button" class="btn btn-danger"
                            data-toggle="modal" data-target="#deleteModal"
                            data-action="{{ route('superadmin.tenants.destroy', $tenant->id) }}"
                            data-name="{{ $tenant->name }}" data-users="{{ $tenant->users_count }}">
                            <i class="fas fa-trash"></i> Hapus Tenant
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Modal Suspend --}}
    <div class="modal fade" id="suspendModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.tenants.suspend', $tenant->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Suspend {{ $tenant->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            Semua pengguna tenant ini akan langsung keluar dari aplikasi dan tidak bisa login sampai
                            tenant diaktifkan kembali. Data tetap utuh.
                        </p>
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="suspend_reason" class="form-control" rows="3" maxlength="255" required
                                placeholder="Contoh: Tunggakan pembayaran 2 bulan">{{ old('suspend_reason') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Suspend Tenant</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('pages.superadmin.tenants._delete-modal')
@endsection

@push('scripts')
    <script>
        $(function () {
            $('#suspendModal').appendTo('body');
        });
    </script>
@endpush
