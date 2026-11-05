@extends('layouts.app')

@section('title', 'Akademik Sekolah')

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $hm = fn($t) => $t instanceof \DateTimeInterface ? $t->format('H:i') : ($t ? substr($t, 0, 5) : '-');
        $tabUrl = fn($key, $extra = []) => route(
            'superadmin.school.academics.index',
            array_filter(
                ['tab' => $key, 'company_id' => request('company_id')] + $extra,
                fn($v) => $v !== null && $v !== '',
            ),
        );
        $hasFilter = collect(request()->except(['tab', 'page']))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();
        $studentUrl = fn($id) => Route::has('superadmin.school.students.show')
            ? route('superadmin.school.students.show', $id)
            : null;
        $classUrl = fn($id) => Route::has('superadmin.school.classes.show')
            ? route('superadmin.school.classes.show', $id)
            : null;
        $fileUrl = fn($p) => \App\Http\Controllers\Web\SuperAdmin\School\SuperAdminAcademicController::fileUrl($p);
        $delBtn = fn(
            $id,
            $label,
            $note = '',
        ) => '<button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" data-toggle="modal" data-target="#schoolActionModal"' .
            ' data-action="' .
            e(route('superadmin.school.academics.destroy', [$tab, $id])) .
            '" data-label="' .
            e('Hapus ' . $label) .
            '" data-note="' .
            e($note) .
            '">
<i class="fas fa-trash"></i></button>';

        $usesYear = in_array($tab, ['curricula', 'schedules', 'events', 'grades', 'promotions'], true);
        $usesClass = in_array($tab, ['schedules', 'events', 'grades', 'promotions', 'materials', 'assignments'], true);
        $usesSubject = in_array($tab, ['schedules', 'grades', 'materials', 'assignments'], true);
        $statusOptions = match ($tab) {
            'subjects' => ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'unused' => 'Tidak dipakai sama sekali'],
            'curricula' => ['active' => 'Aktif', 'inactive' => 'Nonaktif'],
            'schedules' => ['conflict' => 'Hanya yang bentrok'],
            'events' => ['upcoming' => 'Akan datang / berjalan', 'past' => 'Sudah lewat'],
            'promotions' => $promotionStatus,
            'assignments' => [
                'open' => 'Masih dibuka',
                'closed' => 'Sudah lewat batas',
                'ungraded' => 'Ada yang belum dikoreksi',
            ],
            default => [],
        };
        $searchPlaceholder = [
            'subjects' => 'Nama / kode mapel',
            'curricula' => 'Nama kurikulum',
            'schedules' => 'Nama guru',
            'events' => 'Judul kegiatan',
            'grades' => 'Nama / NIS murid, judul nilai',
            'promotions' => 'Nama / NIS murid',
            'materials' => 'Judul / nama guru',
            'assignments' => 'Judul / nama guru',
        ][$tab];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Akademik</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring Sekolah</div>
                    <div class="breadcrumb-item">Akademik</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Mapel, kurikulum, jadwal, kalender, nilai, kenaikan kelas, materi, dan tugas dari semua sekolah.
                    Data dikelola Admin Sekolah &amp; Guru. Di sini Anda memantau, mengunduh, dan menghapus data yang salah.
                </p>

                <ul class="nav nav-pills mb-3 flex-wrap">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item mb-1">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ $tabUrl($key) }}">
                                {{ $label }} <span
                                    class="badge badge-{{ $tab === $key ? 'white' : 'light' }}">{{ number_format($counts[$key]) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{-- Ringkasan khusus --}}
                @if ($tab === 'grades' && $gradeSummary->total)
                    <div class="row">
                        @foreach ([['Jumlah Nilai', number_format($gradeSummary->total), 'primary', 'fa-list-ol'], ['Rata-rata', number_format((float) $gradeSummary->avg, 1), 'info', 'fa-chart-bar'], ['Terendah / Tertinggi', number_format((float) $gradeSummary->min, 0) . ' / ' . number_format((float) $gradeSummary->max, 0), 'secondary', 'fa-arrows-alt-v'], ['Di Bawah 75', number_format((int) $gradeSummary->below), $gradeSummary->below ? 'warning' : 'success', 'fa-arrow-down']] as [$label, $value, $color, $icon])
                            <div class="col-6 col-lg-3">
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i>
                                    </div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>{{ $label }}</h4>
                                        </div>
                                        <div class="card-body" style="font-size:18px">{{ $value }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($tab === 'schedules' && $conflictCount)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Ada <b>{{ $conflictCount }}</b> jadwal yang bentrok
                        (kelas atau guru yang sama di jam yang tumpang-tindih).
                        <a href="{{ $tabUrl('schedules', ['status' => 'conflict']) }}" class="alert-link">Tampilkan</a>
                    </div>
                @endif
                @if ($tab === 'assignments' && $ungraded)
                    <div class="alert alert-light border">
                        <i class="fas fa-hourglass-half text-warning"></i> <b>{{ number_format($ungraded) }}</b> jawaban
                        tugas belum dikoreksi guru.
                        <a href="{{ $tabUrl('assignments', ['status' => 'ungraded']) }}">Lihat tugasnya</a>
                    </div>
                @endif

                <div class="card">
                    {{-- ================= FILTER ================= --}}
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.school.academics.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="['class_id','subject_id'].forEach(n => this.form[n] && (this.form[n].value='')); this.form.submit()">
                                        <option value="">Semua Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($usesYear)
                                    <div class="col-md-2 mb-3">
                                        <select name="academic_year" class="form-control">
                                            <option value="">Semua Tahun</option>
                                            @foreach ($academicYears as $y)
                                                <option value="{{ $y }}" @selected(request('academic_year') === $y)>
                                                    {{ $y }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($usesClass)
                                    <div class="col-md-2 mb-3">
                                        <select name="class_id" class="form-control" @disabled($classOptions->isEmpty())>
                                            <option value="">
                                                {{ $classOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Kelas' }}
                                            </option>
                                            @foreach ($classOptions as $c)
                                                <option value="{{ $c->id }}" @selected((string) request('class_id') === (string) $c->id)>
                                                    {{ $c->name }} · {{ $c->academic_year }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($usesSubject)
                                    <div class="col-md-2 mb-3">
                                        <select name="subject_id" class="form-control" @disabled($subjectOptions->isEmpty())>
                                            <option value="">
                                                {{ $subjectOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Mapel' }}
                                            </option>
                                            @foreach ($subjectOptions as $s)
                                                <option value="{{ $s->id }}" @selected((string) request('subject_id') === (string) $s->id)>
                                                    {{ $s->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control" placeholder="{{ $searchPlaceholder }}">
                                </div>
                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.school.academics.export', request()->query() + ['tab' => $tab]) }}"
                                        class="btn btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>

                            <div class="form-row">
                                @if ($statusOptions)
                                    <div class="col-md-3 mb-3">
                                        <select name="status" class="form-control form-control-sm">
                                            <option value="">Semua</option>
                                            @foreach ($statusOptions as $v => $l)
                                                <option value="{{ $v }}" @selected(request('status') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($tab === 'subjects')
                                    <div class="col-md-2 mb-3">
                                        <select name="kelompok" class="form-control form-control-sm">
                                            <option value="">Semua Kelompok</option>
                                            @foreach ($subjectGroups as $v => $l)
                                                <option value="{{ $v }}" @selected(request('kelompok') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($tab === 'schedules')
                                    <div class="col-md-2 mb-3">
                                        <select name="hari" class="form-control form-control-sm">
                                            <option value="">Semua Hari</option>
                                            @foreach ($days as $v => $l)
                                                <option value="{{ $v }}" @selected(request('hari') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if (in_array($tab, ['events', 'grades'], true))
                                    <div class="col-md-2 mb-3">
                                        <select name="jenis" class="form-control form-control-sm">
                                            <option value="">Semua Jenis</option>
                                            @foreach ($tab === 'events' ? $eventTypes : $gradeTypes as $v => $l)
                                                <option value="{{ $v }}" @selected(request('jenis') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($tab === 'grades')
                                    <div class="col-md-2 mb-3">
                                        <select name="semester" class="form-control form-control-sm">
                                            <option value="">Semua Semester</option>
                                            @foreach ($semesters as $v => $l)
                                                <option value="{{ $v }}" @selected(request('semester') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>

                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.school.academics.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    {{-- ================= TABEL ================= --}}
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">

                                {{-- ---------- MAPEL ---------- --}}
                                @if ($tab === 'subjects')
                                    <thead>
                                        <tr>
                                            <th>Mapel</th>
                                            <th>Sekolah</th>
                                            <th>Kelompok</th>
                                            <th>Tingkat</th>
                                            <th>Dipakai di</th>
                                            <th>Status</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $s)
                                            @php
                                                $usage = collect($subjectUsage)
                                                    ->map(fn($label, $table) => [(int) $s->{$table . '_count'}, $label])
                                                    ->filter(fn($u) => $u[0] > 0);
                                            @endphp
                                            <tr class="{{ $s->is_active ? '' : 'text-muted' }}">
                                                <td><b>{{ $s->name }}</b>
                                                    @if ($s->code)
                                                        <div class="text-small text-muted">{{ $s->code }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $companyNames[$s->company_id] ?? '-' }}</td>
                                                <td>{{ $subjectGroups[$s->kelompok] ?? $s->kelompok }}</td>
                                                <td>
                                                    @if ($s->grade_level_min === null && $s->grade_level_max === null)
                                                        Semua
                                                    @else
                                                        {{ $s->grade_level_min ?? 1 }}–{{ $s->grade_level_max ?? '…' }}
                                                    @endif
                                                </td>
                                                <td class="text-small">
                                                    @forelse ($usage as [$n, $label])
                                                        <span class="badge badge-light">{{ $n }}
                                                            {{ $label }}</span>
                                                    @empty
                                                        <span class="text-muted">Belum dipakai</span>
                                                    @endforelse
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $s->is_active ? 'success' : 'secondary' }}">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                </td>
                                                <td class="text-right">
                                                    @if ($usage->isEmpty())
                                                        {!! $delBtn($s->id, 'mapel ' . $s->name) !!}
                                                    @else
                                                        <span class="text-small text-muted"
                                                            title="Mapel yang masih dipakai tidak bisa dihapus">terpakai</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Tidak ada mapel.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- KURIKULUM ---------- --}}
                                @elseif ($tab === 'curricula')
                                    <thead>
                                        <tr>
                                            <th>Kurikulum</th>
                                            <th>Sekolah</th>
                                            <th>Tahun Ajaran</th>
                                            <th class="text-center">Mapel</th>
                                            <th class="text-center">Jam/Minggu</th>
                                            <th>Status</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $c)
                                            <tr class="{{ $c->is_active ? '' : 'text-muted' }}">
                                                <td>
                                                    <a href="{{ route('superadmin.school.academics.show', ['curricula', $c->id]) }}"
                                                        class="font-weight-bold">{{ $c->name }}</a>
                                                    @if ($c->description)
                                                        <div class="text-small text-muted">
                                                            {{ \Illuminate\Support\Str::limit($c->description, 70) }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $companyNames[$c->company_id] ?? '-' }}</td>
                                                <td>{{ $c->academic_year }}</td>
                                                <td class="text-center">{{ $c->curriculum_subjects_count ?: '-' }}</td>
                                                <td class="text-center">{{ (int) $c->total_hours ?: '-' }}</td>
                                                <td><span
                                                        class="badge badge-{{ $c->is_active ? 'success' : 'secondary' }}">{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.academics.show', ['curricula', $c->id]) }}"
                                                        class="btn btn-sm btn-outline-primary"><i
                                                            class="fas fa-eye"></i></a>
                                                    {!! $delBtn(
                                                        $c->id,
                                                        'kurikulum ' . $c->name,
                                                        'Daftar mapel di kurikulum ini ikut terhapus. Mapelnya sendiri tidak terhapus.',
                                                    ) !!}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Tidak ada
                                                    kurikulum.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- JADWAL ---------- --}}
                                @elseif ($tab === 'schedules')
                                    <thead>
                                        <tr>
                                            <th>Kelas</th>
                                            <th>Hari</th>
                                            <th>Jam</th>
                                            <th>Mapel</th>
                                            <th>Guru</th>
                                            <th>Sekolah</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $s)
                                            <tr>
                                                <td>
                                                    @if ($s->classRoom && $classUrl($s->classRoom->id))
                                                        <a
                                                            href="{{ $classUrl($s->classRoom->id) }}">{{ $s->classRoom->name }}</a>
                                                    @else
                                                        {{ $s->classRoom->name ?? '-' }}
                                                    @endif
                                                    <div class="text-small text-muted">{{ $s->academic_year }}</div>
                                                </td>
                                                <td>{{ $days[$s->hari] ?? $s->hari }}</td>
                                                <td class="text-nowrap">
                                                    {{ $hm($s->jam_mulai) }}–{{ $hm($s->jam_selesai) }}
                                                    @if ($s->class_conflicts)
                                                        <div><span class="badge badge-danger"
                                                                title="Kelas ini punya jadwal lain di jam yang sama">bentrok
                                                                kelas</span></div>
                                                    @endif
                                                    @if ($s->teacher_conflicts)
                                                        <div><span class="badge badge-warning"
                                                                title="Guru ini mengajar di tempat lain di jam yang sama">bentrok
                                                                guru</span></div>
                                                    @endif
                                                </td>
                                                <td>{{ $s->subject->name ?? '-' }}</td>
                                                <td>
                                                    {{ $s->teacher->name ?? '-' }}
                                                    @if ($s->teacher && !$s->teacher->is_active)
                                                        <span class="badge badge-light">nonaktif</span>
                                                    @endif
                                                    @if ($s->teacher && (int) $s->teacher->company_id !== (int) $s->company_id)
                                                        <span class="badge badge-danger">beda sekolah</span>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $companyNames[$s->company_id] ?? '-' }}</td>
                                                <td class="text-right">{!! $delBtn(
                                                    $s->id,
                                                    'jadwal ' . ($s->subject->name ?? '') . ' ' . ($days[$s->hari] ?? '') . ' ' . $hm($s->jam_mulai),
                                                ) !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Tidak ada jadwal
                                                    pelajaran.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- KALENDER ---------- --}}
                                @elseif ($tab === 'events')
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Kegiatan</th>
                                            <th>Jenis</th>
                                            <th>Untuk</th>
                                            <th>Sekolah</th>
                                            <th>Dibuat</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $e)
                                            @php $running = $e->tanggal_mulai && $e->tanggal_selesai && today()->between($e->tanggal_mulai, $e->tanggal_selesai); @endphp
                                            <tr
                                                class="{{ $e->tanggal_selesai && $e->tanggal_selesai->lt(today()) ? 'text-muted' : '' }}">
                                                <td class="text-nowrap">
                                                    {{ $fmtDate($e->tanggal_mulai) }}
                                                    @if ($e->tanggal_selesai && !$e->tanggal_selesai->isSameDay($e->tanggal_mulai))
                                                        <div class="text-small">s/d {{ $fmtDate($e->tanggal_selesai) }}
                                                        </div>
                                                    @endif
                                                    @if ($running)
                                                        <span class="badge badge-success">berlangsung</span>
                                                    @endif
                                                </td>
                                                <td><b>{{ $e->title }}</b>
                                                    @if ($e->description)
                                                        <div class="text-small text-muted">
                                                            {{ \Illuminate\Support\Str::limit($e->description, 80) }}</div>
                                                    @endif
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $eventBadge[$e->jenis] ?? 'light' }}">{{ $eventTypes[$e->jenis] ?? $e->jenis }}</span>
                                                </td>
                                                <td>{{ $e->classRoom->name ?? 'Semua kelas' }}<div
                                                        class="text-small text-muted">{{ $e->academic_year }}</div>
                                                </td>
                                                <td class="text-small">{{ $companyNames[$e->company_id] ?? '-' }}</td>
                                                <td class="text-small">{{ $e->createdBy->name ?? '-' }}</td>
                                                <td class="text-right">{!! $delBtn($e->id, 'kegiatan ' . $e->title) !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Tidak ada kegiatan
                                                    kalender.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- NILAI ---------- --}}
                                @elseif ($tab === 'grades')
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Murid</th>
                                            <th>Mapel</th>
                                            <th>Penilaian</th>
                                            <th class="text-center">Nilai</th>
                                            <th>Semester</th>
                                            <th>Guru</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $g)
                                            @php $n = (float) $g->nilai; @endphp
                                            <tr>
                                                <td class="text-nowrap">{{ $fmtDate($g->tanggal) }}</td>
                                                <td>
                                                    @if ($g->student)
                                                        @if ($studentUrl($g->student->id))
                                                            <a
                                                                href="{{ $studentUrl($g->student->id) }}">{{ $g->student->name }}</a>
                                                        @else
                                                            {{ $g->student->name }}
                                                        @endif
                                                    @endif
                                                    <div class="text-small text-muted">{{ $g->classRoom->name ?? '-' }} ·
                                                        {{ $companyNames[$g->company_id] ?? '' }}</div>
                                                </td>
                                                <td>{{ $g->subject->name ?? '-' }}</td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $g->jenis === 'ujian' ? 'primary' : 'light' }}">{{ $gradeTypes[$g->jenis] ?? $g->jenis }}</span>
                                                    {{ $g->judul }}
                                                    @if ($g->catatan)
                                                        <div class="text-small text-muted">
                                                            {{ \Illuminate\Support\Str::limit($g->catatan, 50) }}</div>
                                                    @endif
                                                </td>
                                                <td
                                                    class="text-center font-weight-bold text-{{ $n >= 85 ? 'success' : ($n >= 75 ? 'dark' : 'danger') }}">
                                                    {{ rtrim(rtrim(number_format($n, 2), '0'), '.') }}</td>
                                                <td class="text-small">{{ $semesters[$g->semester] ?? $g->semester }}<div
                                                        class="text-muted">{{ $g->academic_year }}</div>
                                                </td>
                                                <td class="text-small">{{ $g->teacher->name ?? '-' }}</td>
                                                <td class="text-right">{!! $delBtn($g->id, 'nilai "' . $g->judul . '" ' . ($g->student->name ?? '')) !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Tidak ada nilai.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- KENAIKAN KELAS ---------- --}}
                                @elseif ($tab === 'promotions')
                                    <thead>
                                        <tr>
                                            <th>Murid</th>
                                            <th>Dari</th>
                                            <th>Ke</th>
                                            <th>Hasil</th>
                                            <th>Sekolah</th>
                                            <th>Diproses</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $p)
                                            <tr>
                                                <td>
                                                    @if ($p->student)
                                                        @if ($studentUrl($p->student->id))
                                                            <a
                                                                href="{{ $studentUrl($p->student->id) }}">{{ $p->student->name }}</a>
                                                        @else
                                                            {{ $p->student->name }}
                                                        @endif
                                                        <div class="text-small text-muted">NIS {{ $p->student->nis }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $p->fromClass->name ?? '-' }}<div class="text-small text-muted">
                                                        {{ $p->from_academic_year }}</div>
                                                </td>
                                                <td>{{ $p->toClass->name ?? ($p->status === 'lulus' ? 'Lulus' : '-') }}
                                                    <div class="text-small text-muted">{{ $p->to_academic_year }}</div>
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $promotionBadge[$p->status] ?? 'light' }}">{{ $promotionStatus[$p->status] ?? $p->status }}</span>
                                                </td>
                                                <td class="text-small">{{ $companyNames[$p->company_id] ?? '-' }}</td>
                                                <td class="text-small">{{ $p->processedBy->name ?? '-' }}<div
                                                        class="text-muted">
                                                        {{ optional($p->created_at)->format('d M Y') }}</div>
                                                </td>
                                                <td class="text-right">{!! $delBtn(
                                                    $p->id,
                                                    'riwayat kenaikan ' . ($p->student->name ?? ''),
                                                    'Hanya menghapus catatan riwayat. Kelas murid saat ini TIDAK berubah.',
                                                ) !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Belum ada riwayat
                                                    kenaikan kelas.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- MATERI ---------- --}}
                                @elseif ($tab === 'materials')
                                    <thead>
                                        <tr>
                                            <th>Materi</th>
                                            <th>Kelas</th>
                                            <th>Mapel</th>
                                            <th>Guru</th>
                                            <th>Sekolah</th>
                                            <th>Dibuat</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $m)
                                            <tr>
                                                <td>
                                                    <b>{{ $m->title }}</b>
                                                    @if ($m->file_path)
                                                        <div><a href="{{ $fileUrl($m->file_path) }}" target="_blank"
                                                                class="text-small"><i class="fas fa-paperclip"></i>
                                                                {{ $m->file_name ?: basename($m->file_path) }}</a></div>
                                                    @endif
                                                    @if ($m->description)
                                                        <div class="text-small text-muted">
                                                            {{ \Illuminate\Support\Str::limit($m->description, 80) }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $m->classRoom->name ?? '-' }}</td>
                                                <td>{{ $m->subject->name ?? '-' }}</td>
                                                <td class="text-small">{{ $m->teacher->name ?? '-' }}</td>
                                                <td class="text-small">{{ $companyNames[$m->company_id] ?? '-' }}</td>
                                                <td class="text-small">{{ optional($m->created_at)->format('d M Y') }}
                                                </td>
                                                <td class="text-right">{!! $delBtn($m->id, 'materi ' . $m->title, 'File materi ikut terhapus.') !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Belum ada materi.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- TUGAS ---------- --}}
                                @else
                                    <thead>
                                        <tr>
                                            <th>Tugas</th>
                                            <th>Kelas / Mapel</th>
                                            <th>Guru</th>
                                            <th>Batas Waktu</th>
                                            <th>Pengumpulan</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $a)
                                            @php $pct = $a->class_size ? min(100, round($a->submissions_count / $a->class_size * 100)) : 0; @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.school.academics.show', ['assignments', $a->id]) }}"
                                                        class="font-weight-bold">{{ $a->title }}</a>
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$a->company_id] ?? '' }}</div>
                                                </td>
                                                <td>{{ $a->classRoom->name ?? '-' }}<div class="text-small text-muted">
                                                        {{ $a->subject->name ?? '-' }}</div>
                                                </td>
                                                <td class="text-small">{{ $a->teacher->name ?? '-' }}</td>
                                                <td class="text-nowrap">
                                                    {{ optional($a->deadline)->format('d M Y H:i') }}
                                                    <div
                                                        class="text-small {{ $a->deadline && $a->deadline->isPast() ? 'text-muted' : 'text-success' }}">
                                                        {{ $a->deadline && $a->deadline->isPast() ? 'ditutup' : 'masih dibuka' }}
                                                    </div>
                                                </td>
                                                <td style="min-width:150px">
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-fill mr-2" style="height:8px">
                                                            <div class="progress-bar"
                                                                style="width:{{ $pct }}%"></div>
                                                        </div>
                                                        <span
                                                            class="text-small">{{ $a->submissions_count }}/{{ (int) $a->class_size }}</span>
                                                    </div>
                                                    @if ($a->ungraded_count)
                                                        <div class="text-small text-warning">{{ $a->ungraded_count }}
                                                            belum dikoreksi</div>
                                                    @endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.academics.show', ['assignments', $a->id]) }}"
                                                        class="btn btn-sm btn-outline-primary"><i
                                                            class="fas fa-eye"></i></a>
                                                    {!! $delBtn(
                                                        $a->id,
                                                        'tugas ' . $a->title,
                                                        'Semua jawaban murid (' . $a->submissions_count . ') dan file-nya ikut terhapus.',
                                                    ) !!}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Belum ada tugas.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                @endif
                            </table>
                        </div>
                    </div>

                    @if ($items->hasPages())
                        <div class="card-footer text-right">{{ $items->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
