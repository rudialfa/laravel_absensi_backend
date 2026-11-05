@extends('layouts.app')

@section('title', 'Antrian Job')

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        $s = $status;
        $tabs = [
            'overview' => ['Ringkasan & Scheduler', null],
            'pending' => ['Menunggu', $s['pending']],
            'failed' => ['Gagal', $s['failed']],
            'log' => ['Log Scheduler', null],
        ];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Antrian Job</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Sistem</div>
                    <div class="breadcrumb-item">Antrian Job</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if (session('command_output'))
                    <div class="card">
                        <div class="card-header">
                            <h4>Output perintah</h4>
                        </div>
                        <div class="card-body">
                            <pre class="mb-0 text-small bg-dark text-light p-3 rounded" style="white-space:pre-wrap;max-height:320px;overflow:auto">{{ session('command_output') }}</pre>
                        </div>
                    </div>
                @endif

                {{-- ======== STATUS ======== --}}
                <div class="row">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-{{ $s['scheduler_ok'] ? 'success' : 'danger' }}"><i
                                    class="fas fa-heartbeat"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Scheduler (cron)</h4>
                                </div>
                                <div class="card-body" style="font-size:16px">
                                    @if ($s['scheduler_ok'])
                                        Hidup
                                    @elseif ($s['heartbeat'])
                                        Mati
                                    @else
                                        Belum terdeteksi
                                    @endif
                                    <div class="text-small text-muted font-weight-normal">
                                        {{ $s['heartbeat'] ? 'detak terakhir ' . $s['heartbeat']->diffForHumans() : 'belum pernah berdetak' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div
                                class="card-icon bg-{{ $s['driver'] === 'sync' ? 'secondary' : ($s['worker_ok'] === false ? 'danger' : 'primary') }}">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Driver Queue</h4>
                                </div>
                                <div class="card-body" style="font-size:16px">
                                    {{ $s['connection'] }}
                                    <div class="text-small text-muted font-weight-normal">
                                        @if ($s['driver'] === 'sync')
                                            langsung dijalankan, tanpa worker
                                        @elseif ($s['worker_ok'] === true)
                                            worker merespons uji {{ $s['test_done']->diffForHumans() }}
                                        @elseif ($s['worker_ok'] === false)
                                            uji belum diproses sejak {{ $s['test_sent']->diffForHumans() }}
                                        @else
                                            worker belum diuji
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-{{ $s['pending'] ? 'warning' : 'light' }}"><i
                                    class="fas fa-hourglass-half"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Menunggu</h4>
                                </div>
                                <div class="card-body">
                                    {{ number_format($s['pending']) }}
                                    <div class="text-small text-muted font-weight-normal">
                                        @if ($s['oldest_pending'])
                                            tertua {{ $s['oldest_pending']->diffForHumans() }}
                                        @else
                                            {{ $s['reserved'] ? $s['reserved'] . ' sedang diproses' : 'kosong' }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-{{ $s['failed'] ? 'danger' : 'light' }}"><i
                                    class="fas fa-exclamation-triangle"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Gagal</h4>
                                </div>
                                <div class="card-body">
                                    {{ number_format($s['failed']) }}
                                    <div class="text-small text-muted font-weight-normal">{{ $s['failed_24h'] }} dalam 24
                                        jam</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if (!$s['scheduler_ok'])
                    <div class="alert alert-danger">
                        <b><i class="fas fa-exclamation-circle"></i> Scheduler tidak berjalan.</b>
                        Pengingat absen, notifikasi langganan, pengumuman terjadwal, dan jadwal sholat otomatis tidak akan
                        jalan.
                        <ul class="mb-0 mt-1 pl-3">
                            <li>Server Linux: pasang cron <code>* * * * * cd /path/ke/proyek &amp;&amp; php artisan
                                    schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></li>
                            <li>Komputer lokal: jalankan <code>php artisan schedule:work</code> dan biarkan jendelanya
                                terbuka.</li>
                            <li>Status ini baru terbaca ±1 menit setelah Kernel baru (yang berisi detak scheduler)
                                terpasang.</li>
                        </ul>
                    </div>
                @endif
                @if (
                    $s['driver'] !== 'sync' &&
                        ($s['worker_ok'] === false || ($s['oldest_pending'] && $s['oldest_pending']->lt(now()->subMinutes(5)))))
                    <div class="alert alert-warning">
                        <b><i class="fas fa-exclamation-circle"></i> Worker queue sepertinya tidak berjalan.</b>
                        Jalankan <code>php artisan queue:work --tries=3 --timeout=120</code> (di server, pasang lewat
                        Supervisor/systemd supaya hidup terus).
                    </div>
                @endif

                <ul class="nav nav-pills mb-3">
                    @foreach ($tabs as $key => [$label, $count])
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ route('superadmin.jobs.index', ['tab' => $key]) }}">
                                {{ $label }}
                                @if ($count)
                                    <span
                                        class="badge badge-{{ $tab === $key ? 'white' : ($key === 'failed' ? 'danger' : 'warning') }}">{{ number_format($count) }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{-- ======== RINGKASAN & SCHEDULER ======== --}}
                @if ($tab === 'overview')
                    <div class="card">
                        <div class="card-header">
                            <h4>Tugas Terjadwal ({{ count($events) }})</h4>
                            <div class="card-header-action">
                                <form method="POST" action="{{ route('superadmin.jobs.test') }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-vial"></i> Uji Worker
                                        Queue</button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tugas</th>
                                            <th>Jadwal</th>
                                            <th>Berikutnya</th>
                                            <th>Log terakhir ditulis</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($events as $e)
                                            <tr>
                                                <td>
                                                    @if ($e['artisan'])
                                                        <code>{{ $e['artisan'] }}</code>
                                                    @else
                                                        <span
                                                            class="font-weight-bold">{{ $e['description'] ?: 'closure' }}</span>
                                                        <span class="badge badge-light">fungsi</span>
                                                    @endif
                                                    @if ($e['locked'])
                                                        <span class="badge badge-warning"
                                                            title="Kunci withoutOverlapping aktif — sedang berjalan atau macet">terkunci</span>
                                                    @endif
                                                </td>
                                                <td class="text-small text-nowrap"><code>{{ $e['expression'] }}</code>
                                                    <div class="text-muted">{{ $e['timezone'] }}</div>
                                                </td>
                                                <td class="text-small text-nowrap">
                                                    @if ($e['next'])
                                                        {{ $e['next']->format('d M H:i') }}
                                                        <div class="text-muted">{{ $e['next']->diffForHumans() }}</div>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="text-small">
                                                    @if ($e['output'])
                                                        <a
                                                            href="{{ route('superadmin.jobs.index', ['tab' => 'log', 'file' => $e['output']]) }}">{{ $e['output'] }}</a>
                                                        <div class="text-muted">
                                                            {{ $e['output_at'] ? $e['output_at']->diffForHumans() : 'belum ada' }}
                                                        </div>
                                                    @else
                                                        <span class="text-muted">tidak dicatat</span>
                                                    @endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    @if ($e['artisan'])
                                                        <form method="POST" action="{{ route('superadmin.jobs.run') }}"
                                                            class="d-inline js-run" data-command="{{ $e['artisan'] }}">
                                                            @csrf
                                                            <input type="hidden" name="key"
                                                                value="{{ $e['key'] }}">
                                                            <button class="btn btn-sm btn-outline-success"
                                                                title="Jalankan sekarang"><i
                                                                    class="fas fa-play"></i></button>
                                                        </form>
                                                    @endif
                                                    @if ($e['locked'])
                                                        <form method="POST" action="{{ route('superadmin.jobs.unlock') }}"
                                                            class="d-inline"
                                                            onsubmit="return confirm('Lepas kunci? Lakukan hanya kalau tugas ini macet (tidak benar-benar sedang berjalan).')">
                                                            @csrf
                                                            <input type="hidden" name="key"
                                                                value="{{ $e['key'] }}">
                                                            <button class="btn btn-sm btn-outline-warning"
                                                                title="Lepas kunci"><i class="fas fa-unlock"></i></button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">Tidak ada tugas
                                                    terjadwal di app/Console/Kernel.php.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-small text-muted">
                            <i class="fas fa-info-circle"></i> Tombol <i class="fas fa-play"></i> menjalankan perintah
                            langsung dari web (maks. 5 menit) dan dicatat di Audit Log.
                            Untuk perintah berat, lebih aman lewat terminal server.
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4>Tentang Queue di Aplikasi Ini</h4>
                        </div>
                        <div class="card-body text-small">
                            <p class="mb-2">
                                Saat ini aplikasi <b>belum memakai job queue</b> — notifikasi, pengumuman massal, langganan,
                                dan jadwal sholat
                                semuanya dijalankan lewat <b>scheduler</b> di atas. Karena itu yang paling penting dipantau
                                adalah status
                                <b>Scheduler (cron)</b>.
                            </p>
                            <p class="mb-0">
                                Driver <code>sync</code> sudah cukup. Kalau nanti ada fitur yang memakai
                                <code>ShouldQueue</code>/<code>dispatch()</code>,
                                ganti <code>QUEUE_CONNECTION=database</code> di <code>.env</code> dan jalankan <code>php
                                    artisan queue:work</code> terus-menerus;
                                tab <b>Menunggu</b> dan tombol <b>Uji Worker Queue</b> akan menunjukkan apakah worker hidup.
                            </p>
                        </div>
                    </div>
                @endif

                {{-- ======== MENUNGGU ======== --}}
                @if ($tab === 'pending')
                    <div class="card">
                        <div class="card-header">
                            <h4>Job Menunggu</h4>
                            @if ($queues->count() > 1)
                                <div class="card-header-form">
                                    <form method="GET" action="{{ route('superadmin.jobs.index') }}">
                                        <input type="hidden" name="tab" value="pending">
                                        <select name="queue" class="form-control form-control-sm"
                                            onchange="this.form.submit()">
                                            <option value="">Semua queue</option>
                                            @foreach ($queues as $q)
                                                <option value="{{ $q }}" @selected(request('queue') === $q)>
                                                    {{ $q }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </div>
                            @endif
                        </div>
                        <div class="card-body p-0">
                            @if (!$s['has_jobs'])
                                <div class="p-4 text-center text-muted">Tabel <code>jobs</code> belum ada. Jalankan
                                    <code>php artisan migrate</code>.
                                </div>
                            @else
                                @if ($s['driver'] !== 'database')
                                    <div class="alert alert-light border m-3 text-small">Driver aktif
                                        <b>{{ $s['connection'] }}</b>, jadi tabel <code>jobs</code> tidak dipakai. Daftar
                                        ini hanya berisi sisa job lama (kalau ada).
                                    </div>
                                @endif
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Job</th>
                                                <th>Queue</th>
                                                <th>Percobaan</th>
                                                <th>Dibuat</th>
                                                <th>Siap dijalankan</th>
                                                <th>Status</th>
                                                <th class="text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($jobs as $j)
                                                <tr>
                                                    <td class="text-small">{{ $j->id }}</td>
                                                    <td><span title="{{ $j->name }}">{{ $j->short_name }}</span>
                                                    </td>
                                                    <td class="text-small"><code>{{ $j->queue }}</code></td>
                                                    <td class="text-small">
                                                        {{ $j->attempts }}{{ $j->max_tries ? '/' . $j->max_tries : '' }}
                                                    </td>
                                                    <td class="text-small">
                                                        {{ $j->created->timezone($tz)->format('d M H:i:s') }}</td>
                                                    <td class="text-small">
                                                        {{ $j->available->timezone($tz)->format('d M H:i:s') }}</td>
                                                    <td>
                                                        @if ($j->reserved)
                                                            <span class="badge badge-info">diproses
                                                                {{ $j->reserved->diffForHumans() }}</span>
                                                        @elseif ($j->available->isFuture())
                                                            <span class="badge badge-light">ditunda</span>
                                                        @else
                                                            <span class="badge badge-warning">menunggu</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-right text-nowrap">
                                                        <a href="{{ route('superadmin.jobs.show', ['pending', $j->id]) }}"
                                                            class="btn btn-sm btn-light"><i class="fas fa-eye"></i></a>
                                                        @if (!$j->reserved)
                                                            <form method="POST"
                                                                action="{{ route('superadmin.jobs.destroy', ['pending', $j->id]) }}"
                                                                class="d-inline"
                                                                onsubmit="return confirm('Hapus job ini dari antrian? Job tidak akan dijalankan.')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-sm btn-light text-danger"><i
                                                                        class="fas fa-trash"></i></button>
                                                            </form>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">Tidak ada job
                                                        menunggu.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                        @if ($jobs && $jobs->hasPages())
                            <div class="card-footer d-flex justify-content-end">{{ $jobs->links() }}</div>
                        @endif
                    </div>
                @endif

                {{-- ======== GAGAL ======== --}}
                @if ($tab === 'failed')
                    <div class="card">
                        <div class="card-header">
                            <h4>Job Gagal</h4>
                            <div class="card-header-action">
                                @if ($s['failed'])
                                    <form method="POST" action="{{ route('superadmin.jobs.retry-all') }}"
                                        class="d-inline"
                                        onsubmit="return confirm('Kembalikan semua {{ $s['failed'] }} job gagal ke antrian?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary"><i class="fas fa-redo"></i> Retry
                                            Semua</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal"
                                        data-target="#flushModal"><i class="fas fa-trash"></i> Kosongkan</button>
                                @endif
                            </div>
                        </div>
                        <div class="card-body pb-0">
                            <form method="GET" action="{{ route('superadmin.jobs.index') }}" class="form-row">
                                <input type="hidden" name="tab" value="failed">
                                <div class="col-md-6 mb-2"><input type="text" name="search"
                                        value="{{ request('search') }}" class="form-control"
                                        placeholder="Cari nama job / pesan error…"></div>
                                @if ($queues->count() > 1)
                                    <div class="col-md-3 mb-2">
                                        <select name="queue" class="form-control">
                                            <option value="">Semua queue</option>
                                            @foreach ($queues as $q)
                                                <option value="{{ $q }}" @selected(request('queue') === $q)>
                                                    {{ $q }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block"><i
                                            class="fas fa-search"></i></button></div>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            @if (!$s['has_failed'])
                                <div class="p-4 text-center text-muted">Tabel <code>failed_jobs</code> belum ada. Jalankan
                                    <code>php artisan migrate</code>.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Job</th>
                                                <th>Error</th>
                                                <th>Queue</th>
                                                <th>Gagal</th>
                                                <th class="text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($jobs as $j)
                                                <tr>
                                                    <td class="text-small">{{ $j->id }}</td>
                                                    <td><span title="{{ $j->name }}">{{ $j->short_name }}</span>
                                                    </td>
                                                    <td class="text-small text-danger" style="max-width:420px">
                                                        {{ $j->error }}</td>
                                                    <td class="text-small">
                                                        <code>{{ $j->connection }}/{{ $j->queue }}</code>
                                                    </td>
                                                    <td class="text-small text-nowrap">
                                                        {{ $j->failed->timezone($tz)->format('d M H:i') }}<div
                                                            class="text-muted">{{ $j->failed->diffForHumans() }}</div>
                                                    </td>
                                                    <td class="text-right text-nowrap">
                                                        <a href="{{ route('superadmin.jobs.show', ['failed', $j->id]) }}"
                                                            class="btn btn-sm btn-light"><i class="fas fa-eye"></i></a>
                                                        <form method="POST"
                                                            action="{{ route('superadmin.jobs.retry', $j->id) }}"
                                                            class="d-inline">
                                                            @csrf
                                                            <button class="btn btn-sm btn-light text-primary"
                                                                title="Retry"><i class="fas fa-redo"></i></button>
                                                        </form>
                                                        <form method="POST"
                                                            action="{{ route('superadmin.jobs.destroy', ['failed', $j->id]) }}"
                                                            class="d-inline"
                                                            onsubmit="return confirm('Hapus job gagal ini?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-sm btn-light text-danger"
                                                                title="Hapus"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">Tidak ada job
                                                        gagal.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                        @if ($jobs && $jobs->hasPages())
                            <div class="card-footer d-flex justify-content-end">{{ $jobs->links() }}</div>
                        @endif
                    </div>
                @endif

                {{-- ======== LOG ======== --}}
                @if ($tab === 'log')
                    <div class="card">
                        <div class="card-header">
                            <h4>Log Scheduler</h4>
                            <div class="card-header-form">
                                @if ($files)
                                    <form method="GET" action="{{ route('superadmin.jobs.index') }}">
                                        <input type="hidden" name="tab" value="log">
                                        <select name="file" class="form-control form-control-sm"
                                            onchange="this.form.submit()">
                                            @foreach ($files as $f)
                                                <option value="{{ $f }}" @selected($selected === $f)>
                                                    {{ $f }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            @if (!$files)
                                <div class="text-center text-muted py-4">Belum ada file log scheduler di
                                    <code>storage/logs</code>.
                                </div>
                            @else
                                @if ($fileInfo)
                                    <p class="text-small text-muted mb-2">
                                        {{ number_format($fileInfo['size'] / 1024, 1) }} KB · terakhir ditulis
                                        {{ $fileInfo['mtime']->timezone($tz)->format('d M Y H:i:s') }}
                                        ({{ $fileInfo['mtime']->diffForHumans() }}) · menampilkan {{ count($lines) }}
                                        baris terakhir · dikosongkan otomatis tiap Minggu 03:00
                                    </p>
                                @endif
                                <pre class="bg-dark text-light p-3 rounded text-small mb-0"
                                    style="white-space:pre-wrap;word-break:break-word;max-height:600px;overflow:auto" id="logBox">{{ $lines ? implode("\n", $lines) : '(kosong)' }}</pre>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>

    @if ($tab === 'failed' && $s['failed'])
        <div class="modal fade" id="flushModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('superadmin.jobs.flush') }}">
                    @csrf
                    @method('DELETE')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title text-danger">Kosongkan Job Gagal</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Yang dihapus</label>
                                <select name="older_than" class="form-control">
                                    <option value="30">Lebih dari 30 hari</option>
                                    <option value="7">Lebih dari 7 hari</option>
                                    <option value="1">Lebih dari 1 hari</option>
                                    <option value="0">Semua ({{ $s['failed'] }})</option>
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label>Alasan <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button class="btn btn-danger">Hapus</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(function() {
            $('#flushModal').appendTo('body');

            $('.js-run').on('submit', function(e) {
                if (!confirm('Jalankan sekarang: php artisan ' + $(this).data('command') +
                        ' ?\nHalaman akan menunggu sampai perintah selesai.')) {
                    e.preventDefault();
                    return;
                }
                $(this).find('button').prop('disabled', true).html(
                    '<i class="fas fa-spinner fa-spin"></i>');
            });

            const box = document.getElementById('logBox');
            if (box) box.scrollTop = box.scrollHeight;
        });
    </script>
@endpush
