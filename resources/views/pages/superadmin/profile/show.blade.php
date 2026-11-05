@extends('layouts.app')

@section('title', 'Profil Saya')

@section('main')
    @php $tz = 'Asia/Jakarta'; @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Profil Saya</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Profil</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    {{-- ===== Ringkasan ===== --}}
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <img src="{{ asset('backend/asset/img/avatar/avatar-1.png') }}" alt="Foto profil"
                                    class="rounded-circle mb-3" width="96" height="96">
                                <h5 class="mb-1">{{ $user->name }}</h5>
                                <div class="text-muted small mb-2">{{ $user->email }}</div>
                                <span class="badge badge-primary">{{ ucfirst($user->role ?? '-') }}</span>
                            </div>
                            <div class="card-footer text-small">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Login terakhir</span>
                                    <span>{{ $user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at)->timezone($tz)->format('d M Y H:i') : '-' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Akun dibuat</span>
                                    <span>{{ optional($user->created_at)->timezone($tz)->format('d M Y') ?? '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Aktivitas Terakhir</h4>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-unstyled mb-0">
                                    @forelse ($recent as $log)
                                        <li class="px-3 py-2 border-bottom small">
                                            <div class="text-muted">
                                                {{ $log->created_at->timezone($tz)->format('d M Y H:i') }}</div>
                                            <code>{{ $log->action }}</code>
                                            <div>{{ \Illuminate\Support\Str::limit($log->description, 90) }}</div>
                                        </li>
                                    @empty
                                        <li class="px-3 py-3 text-muted small">Belum ada aktivitas.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="card-footer text-right">
                                <a href="{{ route('superadmin.audit-logs', ['user_id' => $user->id]) }}"
                                    class="btn btn-sm btn-light">Lihat semua</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-8">
                        {{-- ===== Data diri ===== --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Data Diri</h4>
                            </div>
                            <form method="POST" action="{{ route('superadmin.profile.update') }}">
                                @csrf
                                @method('PUT')
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Nama <span class="text-danger">*</span></label>
                                        <input type="text" name="name" value="{{ old('name', $user->name) }}"
                                            class="form-control @error('name') is-invalid @enderror" maxlength="100"
                                            required>
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-group mb-0">
                                        <label>Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                            class="form-control @error('email') is-invalid @enderror" maxlength="255"
                                            required>
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">Email dipakai untuk login dan notifikasi.</small>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Profil</button>
                                </div>
                            </form>
                        </div>

                        {{-- ===== Ganti password ===== --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Ganti Password</h4>
                            </div>
                            <form method="POST" action="{{ route('superadmin.profile.password') }}">
                                @csrf
                                @method('PUT')
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Password lama <span class="text-danger">*</span></label>
                                        <input type="password" name="current_password"
                                            class="form-control @error('current_password') is-invalid @enderror"
                                            autocomplete="current-password" required>
                                        @error('current_password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Password baru <span class="text-danger">*</span></label>
                                            <input type="password" name="password"
                                                class="form-control @error('password') is-invalid @enderror"
                                                autocomplete="new-password" required>
                                            @error('password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Ulangi password baru <span class="text-danger">*</span></label>
                                            <input type="password" name="password_confirmation" class="form-control"
                                                autocomplete="new-password" required>
                                        </div>
                                    </div>
                                    <small class="text-muted">Minimal 8 karakter, berisi huruf dan angka.</small>
                                </div>
                                <div class="card-footer text-right">
                                    <button class="btn btn-warning"><i class="fas fa-key"></i> Ganti Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
