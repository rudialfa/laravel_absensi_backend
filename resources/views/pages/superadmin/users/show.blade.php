@extends('layouts.app')

@section('title', $user->name)

@section('main')
    @php
        $photo = $user->image_url
            ? (str_starts_with($user->image_url, 'http')
                ? $user->image_url
                : asset($user->image_url))
            : null;
        $initials = collect(explode(' ', trim($user->name)))
            ->filter()
            ->take(2)
            ->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.users.index', $user->company_id ? ['company_id' => $user->company_id] : []) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Pengguna</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.users.edit', $user->id) }}" class="btn btn-primary"><i
                            class="fas fa-edit"></i> Edit</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.users.index') }}">Pengguna</a></div>
                    <div class="breadcrumb-item">{{ $user->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                {{-- Password baru (hanya muncul sekali) --}}
                @if (session('generated_password'))
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <strong><i class="fas fa-key"></i> Password baru:</strong>
                                <code id="gen-pass" style="font-size:16px"
                                    class="ml-1">{{ session('generated_password') }}</code>
                                <div class="small mt-1">Catat dan berikan ke pengguna sekarang. Password ini tidak akan
                                    ditampilkan lagi.</div>
                            </div>
                            <button type="button" class="btn btn-light btn-sm mt-2 mt-md-0" id="copy-pass">
                                <i class="fas fa-copy"></i> Salin
                            </button>
                        </div>
                    </div>
                @endif

                @if ($isLastAdmin)
                    <div class="alert alert-light border">
                        <i class="fas fa-shield-alt text-primary"></i>
                        Ini <strong>satu-satunya akun pengelola aktif</strong> di {{ $user->company->name }}.
                        Akun ini tidak bisa dinonaktifkan, dipindah, atau dihapus sebelum ada pengelola lain.
                    </div>
                @endif

                <div class="row">
                    {{-- ── Profil ─────────────────────────────── --}}
                    <div class="col-12 col-lg-4">
                        <div class="card profile-widget mt-0">
                            <div class="card-body text-center pt-4">
                                @if ($photo)
                                    <img src="{{ $photo }}" alt="" class="rounded-circle border mb-3"
                                        style="width:96px;height:96px;object-fit:cover">
                                @else
                                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
                                        style="width:96px;height:96px;font-size:32px;font-weight:700">{{ $initials ?: '?' }}
                                    </div>
                                @endif
                                <h5 class="mb-1">{{ $user->name }}</h5>
                                <div class="text-muted mb-2">{{ $user->email }}</div>
                                <span
                                    class="badge badge-primary">{{ $roleLabels[$user->role] ?? ucfirst($user->role) }}</span>
                                <span class="badge badge-{{ $user->is_active ? 'success' : 'secondary' }}">
                                    {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <div class="card-footer bg-whitesmoke">
                                <a href="{{ route('superadmin.users.edit', $user->id) }}"
                                    class="btn btn-primary btn-block">
                                    <i class="fas fa-edit"></i> Edit Data
                                </a>
                                <button type="button" class="btn btn-outline-primary btn-block" data-toggle="modal"
                                    data-target="#resetModal">
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                                <form action="{{ route('superadmin.users.logout-all', $user->id) }}" method="POST"
                                    onsubmit="return confirm('Keluarkan pengguna ini dari semua perangkat?')">
                                    @csrf
                                    <button class="btn btn-outline-secondary btn-block" @disabled($session['count'] === 0)>
                                        <i class="fas fa-sign-out-alt"></i> Keluarkan dari Semua Perangkat
                                    </button>
                                </form>
                                <form action="{{ route('superadmin.users.toggle-active', $user->id) }}" method="POST"
                                    onsubmit="return confirm('{{ $user->is_active ? 'Nonaktifkan akun ini? Pengguna langsung keluar dari aplikasi.' : 'Aktifkan kembali akun ini?' }}')">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-block {{ $user->is_active ? 'btn-warning' : 'btn-success' }}"
                                        @disabled($user->is_active && $isLastAdmin)>
                                        <i class="fas {{ $user->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                        {{ $user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-8">
                        {{-- ── Data ───────────────────────────── --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Informasi Akun</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:180px">Tenant</th>
                                        <td>
                                            @if ($user->company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $user->company_id) }}">{{ $user->company->name }}</a>
                                                <span
                                                    class="text-muted">({{ $typeLabels[$user->company->type] ?? ucfirst($user->company->type) }})</span>
                                                @unless ($user->company->is_active)
                                                    <span class="badge badge-danger">Tenant disuspend</span>
                                                @endunless
                                            @else
                                                <span class="text-danger">Tidak terhubung ke tenant (tidak bisa login di
                                                    aplikasi)</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>No. HP</th>
                                        <td>{{ $user->phone ?: '-' }}</td>
                                    </tr>
                                    @if ($user->position || $user->department)
                                        <tr>
                                            <th>Jabatan / Departemen</th>
                                            <td>{{ $user->position ?: '-' }} / {{ $user->department ?: '-' }}</td>
                                        </tr>
                                    @endif
                                    @if ($user->company && $user->company->type === 'company')
                                        <tr>
                                            <th>Gaji Pokok</th>
                                            <td>Rp {{ number_format((float) $user->salary, 0, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th>Terdaftar</th>
                                        <td>{{ optional($user->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Terakhir diubah</th>
                                        <td>{{ optional($user->updated_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        {{-- ── Perangkat & sesi ───────────────── --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Login &amp; Notifikasi</h4>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="h4 mb-0">{{ $session['count'] }}</div>
                                        <small class="text-muted">Sesi login aktif</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h6 mb-0 mt-1">
                                            {{ $session['last_used'] ? \Carbon\Carbon::parse($session['last_used'])->diffForHumans() : '-' }}
                                        </div>
                                        <small class="text-muted">Terakhir memakai aplikasi</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h6 mb-0 mt-1">
                                            @if ($user->fcm_token)
                                                <span class="text-success"><i class="fas fa-bell"></i> Terhubung</span>
                                            @else
                                                <span class="text-muted"><i class="fas fa-bell-slash"></i> Belum ada</span>
                                            @endif
                                        </div>
                                        <small class="text-muted">Push notifikasi</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ── Aktivitas sesuai peran ─────────── --}}
                        @if (!empty($activity))
                            <div class="card">
                                <div class="card-header">
                                    <h4>Aktivitas</h4>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        @foreach ($activity as $label => $value)
                                            <tr>
                                                <td class="pl-4">{{ $label }}</td>
                                                <td class="text-right pr-4"><strong>{{ $value }}</strong></td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- ── Anak (khusus wali) ─────────────── --}}
                        @if ($user->role === 'wali')
                            <div class="card">
                                <div class="card-header">
                                    <h4>Anak / Murid Terhubung ({{ $children->count() }})</h4>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th class="pl-4">Nama</th>
                                                <th>NIS</th>
                                                <th>Hubungan</th>
                                                <th>Wali utama</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($children as $child)
                                                <tr>
                                                    <td class="pl-4">{{ $child->name }}</td>
                                                    <td>{{ $child->nis }}</td>
                                                    <td>{{ ucfirst($child->pivot->relationship ?? '-') }}</td>
                                                    <td>{{ $child->pivot->is_primary ? 'Ya' : '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted">Belum terhubung ke
                                                        murid mana pun</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- ── Zona berbahaya ─────────────────── --}}
                        <div class="card border border-danger">
                            <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                                <div class="mb-2 mb-md-0">
                                    <h6 class="text-danger mb-1">Hapus Pengguna</h6>
                                    <span class="text-muted small">Absensi, laporan, dan data lain milik pengguna ini ikut
                                        terhapus.
                                        Lebih aman pakai <b>Nonaktifkan</b>.</span>
                                </div>
                                <button type="button" class="btn btn-danger" data-toggle="modal"
                                    data-target="#deleteModal" @disabled($isLastAdmin)>
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Modal reset password --}}
    <div class="modal fade" id="resetModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.users.reset-password', $user->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reset Password {{ $user->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Kosongkan untuk membuat password acak. Pengguna akan keluar dari semua
                            perangkat.</p>
                        <div class="form-group">
                            <label>Password baru</label>
                            <input type="password" name="new_password" minlength="6" class="form-control"
                                autocomplete="new-password">
                        </div>
                        <div class="form-group mb-0">
                            <label>Ulangi password baru</label>
                            <input type="password" name="new_password_confirmation" minlength="6" class="form-control"
                                autocomplete="new-password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal hapus --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.users.destroy', $user->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Hapus Pengguna
                            Permanen</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p>Ketik email <strong>{{ $user->email }}</strong> untuk konfirmasi:</p>
                        <input type="text" name="confirm_email" id="confirmEmail" class="form-control"
                            autocomplete="off" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>Hapus
                            Permanen</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            // Stisla: pindahkan modal ke <body> supaya tidak tertutup backdrop
            $('#resetModal, #deleteModal').appendTo('body');

            const expected = @json($user->email);
            $('#confirmEmail').on('input', function() {
                $('#confirmDeleteBtn').prop('disabled', this.value.trim() !== expected);
            });

            $('#copy-pass').on('click', function() {
                const text = $('#gen-pass').text().trim();
                navigator.clipboard?.writeText(text).then(() => $(this).html(
                    '<i class="fas fa-check"></i> Tersalin'));
            });

            @if ($errors->has('new_password'))
                $('#resetModal').modal('show');
            @endif
        });
    </script>
@endpush
