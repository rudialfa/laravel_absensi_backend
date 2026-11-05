@extends('layouts.app')

@section('title', 'Pengaturan Sistem')

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        $s = $settings;
        $maint = (bool) old('maintenance_mode', $s['maintenance_mode']);
        $gb = fn($b) => $b === null ? '-' : number_format($b / 1073741824, 1) . ' GB';
        $yes = fn($ok, $y = 'Ya', $n = 'Tidak') => '<span class="badge badge-' . ($ok ? 'success' : 'secondary') . '">' . ($ok ? $y : $n) . '</span>';
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Pengaturan Sistem</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Sistem</div>
                    <div class="breadcrumb-item">Pengaturan</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                {{-- ===== Status maintenance ===== --}}
                @if ($s['maintenance_mode'])
                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between">
                        <div class="mr-3">
                            <b><i class="fas fa-tools"></i> Mode maintenance sedang AKTIF.</b>
                            Aplikasi HP, kiosk, dan web tenant menampilkan pesan maintenance.
                            @if ($s['maintenance_until'])
                                Perkiraan selesai {{ \Carbon\Carbon::parse($s['maintenance_until'])->translatedFormat('d M Y H:i') }} WIB.
                            @endif
                        </div>
                        <form method="POST" action="{{ route('superadmin.settings.toggle-maintenance') }}" class="mt-2 mt-md-0">
                            @csrf
                            <button class="btn btn-dark"><i class="fas fa-power-off"></i> Matikan Maintenance</button>
                        </form>
                    </div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <form method="POST" action="{{ route('superadmin.settings.update') }}">
                            @csrf
                            @method('PUT')

                            {{-- ===== UMUM ===== --}}
                            <div class="card">
                                <div class="card-header"><h4><i class="fas fa-sliders-h mr-1"></i> Umum</h4></div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Nama Aplikasi</label>
                                        <input type="text" name="app_name" value="{{ old('app_name', $s['app_name']) }}" class="form-control @error('app_name') is-invalid @enderror" maxlength="100" placeholder="{{ config('app.name') }}">
                                        @error('app_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="text-muted">Dipakai sebagai nama aplikasi di judul halaman, email, dan halaman maintenance. Kosongkan untuk memakai APP_NAME di .env.</small>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Email Support</label>
                                            <input type="email" name="support_email" value="{{ old('support_email', $s['support_email']) }}" class="form-control @error('support_email') is-invalid @enderror" placeholder="cs@domain-anda.id">
                                            @error('support_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>WhatsApp Support</label>
                                            <input type="text" name="support_whatsapp" value="{{ old('support_whatsapp', $s['support_whatsapp']) }}" class="form-control @error('support_whatsapp') is-invalid @enderror" placeholder="081234567890">
                                            @error('support_whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-n2">Email & WhatsApp ini nanti ditampilkan di halaman maintenance dan landing page.</small>
                                </div>
                            </div>

                            {{-- ===== LANGGANAN ===== --}}
                            <div class="card">
                                <div class="card-header"><h4><i class="fas fa-receipt mr-1"></i> Langganan</h4></div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Masa Toleransi (hari) <span class="text-danger">*</span></label>
                                            <input type="number" name="grace_period_days" value="{{ old('grace_period_days', $s['grace_period_days']) }}" class="form-control @error('grace_period_days') is-invalid @enderror" min="0" max="30" required>
                                            @error('grace_period_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small class="text-muted">
                                                Setelah masa aktif habis, tenant masih bisa memakai aplikasi selama sekian hari (status <b>grace</b>) sebelum dikunci.
                                                Isi 0 untuk langsung dikunci. Saat ini {{ $graceCount }} langganan sedang dalam masa toleransi.
                                            </small>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Durasi Trial</label>
                                            @if ($trialPlan)
                                                <div class="form-control-plaintext">
                                                    <b>{{ $trialPlan->duration_days }} hari</b> — dari paket gratis <b>{{ $trialPlan->name }}</b>
                                                </div>
                                                <a href="{{ route('superadmin.plans.edit', $trialPlan->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen"></i> Ubah di menu Paket</a>
                                            @else
                                                <div class="alert alert-danger mb-0 py-2 text-small">
                                                    Belum ada paket gratis yang aktif, jadi tenant baru <b>tidak bisa memulai trial</b>.
                                                    <a href="{{ route('superadmin.plans.create') }}" class="alert-link">Buat paket gratis</a>.
                                                </div>
                                            @endif
                                            <small class="text-muted d-block mt-1">Durasi trial mengikuti paket gratis supaya tidak ada dua pengaturan yang bertabrakan.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ===== MAINTENANCE ===== --}}
                            <div class="card">
                                <div class="card-header"><h4><i class="fas fa-tools mr-1"></i> Mode Maintenance</h4></div>
                                <div class="card-body">
                                    <div class="custom-control custom-switch mb-3">
                                        <input type="hidden" name="maintenance_mode" value="0">
                                        <input type="checkbox" name="maintenance_mode" value="1" class="custom-control-input" id="maintenance_mode" @checked($maint)>
                                        <label class="custom-control-label font-weight-bold" for="maintenance_mode">Aktifkan mode maintenance</label>
                                    </div>
                                    <p class="text-small text-muted">
                                        Saat aktif: aplikasi HP & kiosk menerima respons <code>503</code> dengan <code>code: "maintenance"</code>, web tenant menampilkan halaman maintenance.
                                        <b>Panel superadmin, halaman login, dan callback pembayaran tetap jalan.</b>
                                    </p>

                                    <div class="form-group">
                                        <label>Pesan untuk pengguna</label>
                                        <textarea name="maintenance_message" class="form-control @error('maintenance_message') is-invalid @enderror" rows="2" maxlength="500" style="height:auto" placeholder="Aplikasi sedang dalam perbaikan. Silakan coba beberapa saat lagi.">{{ old('maintenance_message', $s['maintenance_message']) }}</textarea>
                                        @error('maintenance_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-group">
                                        <label>Perkiraan selesai (WIB)</label>
                                        <input type="datetime-local" name="maintenance_until" value="{{ old('maintenance_until', $s['maintenance_until']) }}" class="form-control @error('maintenance_until') is-invalid @enderror" style="max-width:280px">
                                        @error('maintenance_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="text-muted">Opsional. Ditampilkan ke pengguna; maintenance <b>tidak</b> mati otomatis.</small>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>IP pengecualian</label>
                                            <textarea name="maintenance_allowed_ips" class="form-control @error('maintenance_allowed_ips') is-invalid @enderror" rows="3" style="height:auto" placeholder="Satu IP per baris">{{ old('maintenance_allowed_ips', $s['maintenance_allowed_ips']) }}</textarea>
                                            @error('maintenance_allowed_ips')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small class="text-muted">IP yang tetap bisa memakai aplikasi (untuk tes). IP Anda: <code>{{ $myIp }}</code>
                                                <a href="#" id="addMyIp" data-ip="{{ $myIp }}">tambahkan</a></small>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Path pengecualian tambahan</label>
                                            <textarea name="maintenance_except_paths" class="form-control @error('maintenance_except_paths') is-invalid @enderror" rows="3" style="height:auto" placeholder="api/payment/notify&#10;api/public/*">{{ old('maintenance_except_paths', $s['maintenance_except_paths']) }}</textarea>
                                            @error('maintenance_except_paths')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small class="text-muted">Satu per baris, boleh pakai <code>*</code>. Sudah otomatis dikecualikan: <code>superadmin/*</code>, <code>login</code>, <code>api/*callback*</code>, <code>api/*webhook*</code>, <code>api/*midtrans*</code>.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                                    <span class="text-small text-muted mb-2 mb-md-0">
                                        @if ($lastChange)
                                            Terakhir diubah {{ $lastChange->created_at->timezone($tz)->format('d M Y H:i') }} oleh {{ $lastChange->user->name ?? 'Sistem' }}
                                            · <a href="{{ route('superadmin.audit-logs', ['search' => 'Pengaturan sistem']) }}">riwayat</a>
                                        @else
                                            Belum pernah diubah.
                                        @endif
                                    </span>
                                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pengaturan</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- ===== KANAN: aksi cepat & info ===== --}}
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header"><h4>Aksi Cepat</h4></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('superadmin.settings.toggle-maintenance') }}" class="mb-2"
                                    onsubmit="return confirm('{{ $s['maintenance_mode'] ? 'Matikan mode maintenance?' : 'Aktifkan mode maintenance sekarang? Semua pengguna aplikasi akan melihat pesan maintenance.' }}')">
                                    @csrf
                                    <button class="btn btn-block {{ $s['maintenance_mode'] ? 'btn-dark' : 'btn-outline-warning' }}">
                                        <i class="fas fa-power-off"></i> {{ $s['maintenance_mode'] ? 'Matikan Maintenance' : 'Aktifkan Maintenance' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.settings.clear-cache') }}" class="mb-2" onsubmit="return confirm('Bersihkan cache konfigurasi, route, dan view?')">
                                    @csrf
                                    <button class="btn btn-block btn-outline-secondary"><i class="fas fa-broom"></i> Bersihkan Cache</button>
                                </form>
                                @if (\Illuminate\Support\Facades\Route::has('superadmin.jobs.index'))
                                    <a href="{{ route('superadmin.jobs.index') }}" class="btn btn-block btn-outline-primary"><i class="fas fa-tasks"></i> Antrian Job & Scheduler</a>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h4>Info Sistem</h4></div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0 text-small">
                                    <tr><th class="pl-3">PHP</th><td>{{ $system['php'] }}</td></tr>
                                    <tr><th class="pl-3">Laravel</th><td>{{ $system['laravel'] }}</td></tr>
                                    <tr>
                                        <th class="pl-3">Environment</th>
                                        <td>
                                            {{ $system['env'] }}
                                            @if ($system['debug'])
                                                <span class="badge badge-{{ $system['env'] === 'production' ? 'danger' : 'warning' }}">debug ON</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr><th class="pl-3">APP_URL</th><td style="word-break:break-all">{{ $system['url'] }}</td></tr>
                                    <tr><th class="pl-3">Timezone</th><td>{{ $system['timezone'] }}</td></tr>
                                    <tr><th class="pl-3">Database</th><td>{{ $system['db'] }}</td></tr>
                                    <tr><th class="pl-3">Cache / Session</th><td>{{ $system['cache'] }} / {{ $system['session'] }}</td></tr>
                                    <tr><th class="pl-3">Queue / Mail</th><td>{{ $system['queue'] }} / {{ $system['mail'] }}</td></tr>
                                    <tr><th class="pl-3">Config / Route cache</th><td>{!! $yes($system['config_cached']) !!} {!! $yes($system['routes_cached']) !!}</td></tr>
                                    <tr><th class="pl-3">storage:link</th><td>{!! $yes($system['storage_link'], 'Ada', 'Belum') !!}</td></tr>
                                    <tr><th class="pl-3">Folder log</th><td>{!! $yes($system['logs_writable'], 'Bisa ditulis', 'Tidak bisa ditulis') !!}</td></tr>
                                    <tr><th class="pl-3">Disk</th><td>{{ $gb($system['disk_free']) }} kosong dari {{ $gb($system['disk_total']) }}</td></tr>
                                    <tr>
                                        <th class="pl-3">Scheduler</th>
                                        <td>
                                            {!! $yes($system['scheduler_ok'], 'Hidup', 'Mati') !!}
                                            @if ($system['scheduler'])<div class="text-muted">detak {{ $system['scheduler']->diffForHumans() }}</div>@endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            @if ($system['debug'] && $system['env'] === 'production')
                                <div class="card-footer text-small text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> <b>APP_DEBUG=true di production</b> — pesan error lengkap (termasuk isi konfigurasi) bisa terlihat orang lain. Ubah ke <code>false</code> di .env server.
                                </div>
                            @endif
                            @if (!$system['storage_link'])
                                <div class="card-footer text-small text-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Foto & lampiran tidak akan tampil sebelum menjalankan <code>php artisan storage:link</code>.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        $('#addMyIp').on('click', function (e) {
            e.preventDefault();
            const ip = $(this).data('ip');
            const box = $('textarea[name="maintenance_allowed_ips"]');
            const lines = box.val().split(/\r?\n/).map(s => s.trim()).filter(Boolean);
            if (!lines.includes(ip)) lines.push(ip);
            box.val(lines.join('\n'));
        });
    </script>
@endpush