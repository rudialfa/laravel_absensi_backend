@extends('layouts.app')

@section('title', 'Kelas, Murid & Wali')

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $hasFilter = collect(request()->except(['tab', 'page']))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();
        $tabUrl = fn($key, $extra = []) => route(
            'superadmin.school.classes.index',
            array_filter(
                ['tab' => $key, 'company_id' => request('company_id')] + $extra,
                fn($v) => $v !== null && $v !== '',
            ),
        );
        $userUrl = fn($id) => Route::has('superadmin.users.show') ? route('superadmin.users.show', $id) : null;
        $actionBtn = fn(
            $action,
            $label,
            $icon = 'fa-trash',
            $method = 'DELETE',
            $color = 'danger',
            $note = '',
        ) => '<button type="button" class="btn btn-sm btn-outline-' .
            $color .
            '" title="' .
            e($label) .
            '" data-toggle="modal" data-target="#schoolActionModal"' .
            ' data-action="' .
            e($action) .
            '" data-label="' .
            e($label) .
            '" data-method="' .
            $method .
            '" data-note="' .
            e($note) .
            '"><i class="fas ' .
            $icon .
            '"></i></button>';
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Kelas, Murid &amp; Wali</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring Sekolah</div>
                    <div class="breadcrumb-item">Kelas, Murid &amp; Wali</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Data kelas, murid, wali, dan guru dari semua sekolah &amp; pesantren. Data ini dikelola Admin Sekolah
                    lewat aplikasi.
                    Di sini Anda bisa memantau, mencari data bermasalah, mengunduh, dan memperbaiki data yang salah.
                </p>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ $tabUrl($key) }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>

                {{-- ================= RINGKASAN ================= --}}
                @php
                    $cards = match ($tab) {
                        'students' => [
                            [
                                'Murid Aktif',
                                number_format($summary['active']) . ' / ' . number_format($summary['total']),
                                'primary',
                                'fa-user-graduate',
                                ['status' => 'active'],
                            ],
                            [
                                'Tinggal di Asrama',
                                number_format($summary['boarding']),
                                'info',
                                'fa-bed',
                                ['boarding' => '1', 'status' => 'active'],
                            ],
                            [
                                'Belum Punya Kelas',
                                number_format($summary['no_class']),
                                $summary['no_class'] ? 'warning' : 'success',
                                'fa-door-open',
                                ['issue' => 'no_class'],
                            ],
                            [
                                'Belum Punya Wali',
                                number_format($summary['no_guardian']),
                                $summary['no_guardian'] ? 'danger' : 'success',
                                'fa-user-slash',
                                ['issue' => 'no_guardian'],
                            ],
                        ],
                        'guardians' => [
                            ['Akun Wali', number_format($summary['total']), 'primary', 'fa-users', []],
                            [
                                'Akun Aktif',
                                number_format($summary['active']),
                                'success',
                                'fa-user-check',
                                ['status' => 'active'],
                            ],
                            ['Hubungan Wali–Murid', number_format($summary['links']), 'info', 'fa-link', []],
                            [
                                'Wali Tanpa Anak',
                                number_format($summary['no_child']),
                                $summary['no_child'] ? 'warning' : 'success',
                                'fa-unlink',
                                ['issue' => 'no_child'],
                            ],
                        ],
                        'teachers' => [
                            [
                                'Penugasan Guru',
                                number_format($summary['total']),
                                'primary',
                                'fa-chalkboard-teacher',
                                [],
                            ],
                            [
                                'Wali Kelas',
                                number_format($summary['homeroom']),
                                'success',
                                'fa-user-tie',
                                ['role_in_class' => 'wali_kelas'],
                            ],
                            [
                                'Guru Mapel',
                                number_format($summary['subject']),
                                'info',
                                'fa-book',
                                ['role_in_class' => 'guru_mapel'],
                            ],
                            [
                                'Guru Belum Ada Kelas',
                                number_format($summary['idle_teachers']),
                                $summary['idle_teachers'] ? 'warning' : 'success',
                                'fa-user-clock',
                                null,
                            ],
                        ],
                        default => [
                            [
                                'Kelas Aktif',
                                number_format((int) $summary->active) . ' / ' . number_format((int) $summary->total),
                                'primary',
                                'fa-school',
                                ['status' => 'active'],
                            ],
                            ['Murid Aktif di Kelas', number_format($studentsActive), 'info', 'fa-user-graduate', null],
                            [
                                'Tanpa Wali Kelas',
                                number_format((int) $summary->no_homeroom),
                                $summary->no_homeroom ? 'warning' : 'success',
                                'fa-user-times',
                                ['issue' => 'no_homeroom'],
                            ],
                            [
                                'Diarsipkan',
                                number_format((int) $summary->total - (int) $summary->active),
                                'secondary',
                                'fa-archive',
                                ['status' => 'archived'],
                            ],
                        ],
                    };
                @endphp
                <div class="row">
                    @foreach ($cards as [$label, $value, $color, $icon, $filter])
                        <div class="col-6 col-lg-3">
                            @if (is_array($filter) && $filter)
                                <a href="{{ $tabUrl($tab, $filter) }}" class="text-decoration-none">
                            @endif
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>{{ $label }}</h4>
                                    </div>
                                    <div class="card-body" style="font-size:18px">{{ $value }}</div>
                                </div>
                            </div>
                            @if (is_array($filter) && $filter)
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($tab === 'students' && $summary['trashed'] > 0)
                    <div class="alert alert-light border">
                        <i class="fas fa-trash-restore text-muted"></i>
                        Ada {{ number_format($summary['trashed']) }} murid terhapus.
                        <a href="{{ $tabUrl('students', ['status' => 'trashed']) }}">Lihat dan pulihkan</a>
                    </div>
                @endif

                <div class="card">
                    {{-- ================= FILTER ================= --}}
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.school.classes.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="this.form.class_id && (this.form.class_id.value=''); this.form.submit()">
                                        <option value="">Semua Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}{{ $s->type === 'pesantren' ? ' (Pesantren)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                @if ($tab !== 'guardians')
                                    <div class="col-md-2 mb-3">
                                        <select name="academic_year" class="form-control">
                                            <option value="">Semua Tahun Ajaran</option>
                                            @foreach ($academicYears as $y)
                                                <option value="{{ $y }}" @selected(request('academic_year') === $y)>
                                                    {{ $y }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                @if ($tab !== 'classes')
                                    <div class="col-md-2 mb-3">
                                        <select name="class_id" class="form-control" @disabled($classOptions->isEmpty())
                                            title="{{ $classOptions->isEmpty() ? 'Pilih sekolah dulu' : '' }}">
                                            <option value="">
                                                {{ $classOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Kelas' }}
                                            </option>
                                            @foreach ($classOptions as $c)
                                                <option value="{{ $c->id }}" @selected((string) request('class_id') === (string) $c->id)>
                                                    {{ $c->name }} ·
                                                    {{ $c->academic_year }}{{ $c->is_active ? '' : ' (arsip)' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                @if ($tab === 'classes')
                                    <div class="col-md-2 mb-3">
                                        <select name="grade_level" class="form-control">
                                            <option value="">Semua Tingkat</option>
                                            @for ($g = 1; $g <= 12; $g++)
                                                <option value="{{ $g }}" @selected((string) request('grade_level') === (string) $g)>Tingkat
                                                    {{ $g }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Aktif &amp; Arsip</option>
                                            <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                            <option value="archived" @selected(request('status') === 'archived')>Arsip</option>
                                        </select>
                                    </div>
                                @elseif ($tab === 'students')
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua Status</option>
                                            <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif / Lulus</option>
                                            <option value="trashed" @selected(request('status') === 'trashed')>Terhapus</option>
                                        </select>
                                    </div>
                                @elseif ($tab === 'guardians')
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua Akun</option>
                                            <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                                        </select>
                                    </div>
                                @else
                                    <div class="col-md-2 mb-3">
                                        <select name="role_in_class" class="form-control">
                                            <option value="">Semua Peran</option>
                                            @foreach ($roleLabels as $v => $l)
                                                <option value="{{ $v }}" @selected(request('role_in_class') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="col-md mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control"
                                        placeholder="{{ ['classes' => 'Nama kelas', 'students' => 'Nama / NIS / NISN', 'guardians' => 'Nama / email / HP', 'teachers' => 'Nama guru / mapel'][$tab] }}">
                                </div>
                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.school.classes.export', request()->query() + ['tab' => $tab]) }}"
                                        class="btn btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>

                            @if ($tab === 'students')
                                <div class="form-row">
                                    <div class="col-md-2 mb-3">
                                        <select name="gender" class="form-control form-control-sm">
                                            <option value="">L &amp; P</option>
                                            @foreach ($genders as $v => $l)
                                                <option value="{{ $v }}" @selected(request('gender') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="boarding" class="form-control form-control-sm">
                                            <option value="">Asrama &amp; Non-asrama</option>
                                            <option value="1" @selected(request('boarding') === '1')>Tinggal di asrama</option>
                                            <option value="0" @selected(request('boarding') === '0')>Pulang-pergi</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <select name="issue" class="form-control form-control-sm">
                                            <option value="">Semua data</option>
                                            <option value="no_class" @selected(request('issue') === 'no_class')>Belum punya kelas
                                            </option>
                                            <option value="no_guardian" @selected(request('issue') === 'no_guardian')>Belum punya wali
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            @elseif ($tab === 'classes')
                                <div class="form-row">
                                    <div class="col-md-3 mb-3">
                                        <select name="issue" class="form-control form-control-sm">
                                            <option value="">Semua kelas</option>
                                            <option value="no_homeroom" @selected(request('issue') === 'no_homeroom')>Belum ada wali kelas
                                            </option>
                                            <option value="empty" @selected(request('issue') === 'empty')>Kelas kosong (tanpa murid)
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            @elseif ($tab === 'guardians')
                                <div class="form-row">
                                    <div class="col-md-3 mb-3">
                                        <select name="issue" class="form-control form-control-sm">
                                            <option value="">Semua wali</option>
                                            <option value="no_child" @selected(request('issue') === 'no_child')>Belum terhubung ke murid
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            @endif

                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.school.classes.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    {{-- ================= TABEL ================= --}}
                    <div class="card-body p-0">
                        <div class="table-responsive">

                            {{-- ---------- KELAS ---------- --}}
                            @if ($tab === 'classes')
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Kelas</th>
                                            <th>Sekolah</th>
                                            <th>Wali Kelas</th>
                                            <th class="text-center">Murid</th>
                                            <th class="text-center">Guru</th>
                                            <th class="text-center">Kiosk</th>
                                            <th class="text-center">Absen Hari Ini</th>
                                            <th>Status</th>
                                            <th class="text-right" style="width:130px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $c)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.school.classes.show', $c->id) }}"
                                                        class="font-weight-bold">{{ $c->name }}</a>
                                                    <div class="text-small text-muted">Tingkat {{ $c->grade_level }} ·
                                                        {{ $c->academic_year }}</div>
                                                </td>
                                                <td><a
                                                        href="{{ $tabUrl('classes', ['company_id' => $c->company_id]) }}">{{ $companyNames[$c->company_id] ?? '#' . $c->company_id }}</a>
                                                </td>
                                                <td>
                                                    @if ($c->homeroomTeacher)
                                                        {{ $c->homeroomTeacher->name }}
                                                        @unless ($c->homeroomTeacher->is_active)
                                                            <span class="badge badge-light">nonaktif</span>
                                                        @endunless
                                                    @else
                                                        <span class="text-warning"><i
                                                                class="fas fa-exclamation-triangle"></i> Belum ada</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <a
                                                        href="{{ $tabUrl('students', ['company_id' => $c->company_id, 'class_id' => $c->id]) }}">{{ $c->students_count }}</a>
                                                </td>
                                                <td class="text-center">{{ $c->class_teachers_count }}</td>
                                                <td class="text-center">{{ $c->attendance_devices_count ?: '-' }}</td>
                                                <td class="text-center">
                                                    @if ($c->today_recorded)
                                                        <span class="text-success">{{ $c->today_present }}</span> /
                                                        {{ $c->students_count }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $c->is_active ? 'success' : 'secondary' }}">{{ $c->is_active ? 'Aktif' : 'Arsip' }}</span>
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.classes.show', $c->id) }}"
                                                        class="btn btn-sm btn-outline-primary" title="Detail"><i
                                                            class="fas fa-eye"></i></a>
                                                    {!! $actionBtn(
                                                        route('superadmin.school.classes.toggle-active', $c->id),
                                                        ($c->is_active ? 'Arsipkan' : 'Aktifkan') . ' Kelas ' . $c->name,
                                                        $c->is_active ? 'fa-archive' : 'fa-box-open',
                                                        'PATCH',
                                                        'warning',
                                                        $c->is_active
                                                            ? 'Kelas arsip tidak muncul sebagai kelas aktif di aplikasi. Murid & histori tetap ada.'
                                                            : 'Kelas akan muncul lagi sebagai kelas aktif di aplikasi.',
                                                    ) !!}
                                                    @if ($c->students_count === 0 && $c->today_recorded === 0)
                                                        {!! $actionBtn(
                                                            route('superadmin.school.classes.destroy', $c->id),
                                                            'Hapus Kelas ' . $c->name,
                                                            'fa-trash',
                                                            'DELETE',
                                                            'danger',
                                                            'Hanya kelas yang tidak punya murid, absen, jadwal, nilai, atau kiosk yang bisa dihapus.',
                                                        ) !!}
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">Tidak ada kelas.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>

                                {{-- ---------- MURID ---------- --}}
                            @elseif ($tab === 'students')
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Murid</th>
                                            <th>Sekolah</th>
                                            <th>Kelas</th>
                                            <th>L/P</th>
                                            <th>Tgl Lahir</th>
                                            <th class="text-center">Wali</th>
                                            <th>Status</th>
                                            <th class="text-right" style="width:90px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $s)
                                            <tr class="{{ $s->trashed() ? 'text-muted' : '' }}">
                                                <td>
                                                    <a href="{{ route('superadmin.school.students.show', $s->id) }}"
                                                        class="font-weight-bold">{{ $s->name }}</a>
                                                    <div class="text-small text-muted">NIS
                                                        {{ $s->nis }}{{ $s->nisn ? ' · NISN ' . $s->nisn : '' }}
                                                    </div>
                                                </td>
                                                <td>{{ $companyNames[$s->company_id] ?? '#' . $s->company_id }}</td>
                                                <td>
                                                    @if ($s->classRoom)
                                                        <a
                                                            href="{{ route('superadmin.school.classes.show', $s->classRoom->id) }}">{{ $s->classRoom->name }}</a>
                                                        <div class="text-small text-muted">
                                                            {{ $s->classRoom->academic_year }}</div>
                                                    @else
                                                        <span class="text-warning">Belum ada</span>
                                                    @endif
                                                </td>
                                                <td>{{ $s->gender }}</td>
                                                <td>{{ $fmtDate($s->birth_date) }}</td>
                                                <td class="text-center">
                                                    @if ($s->guardians_count)
                                                        {{ $s->guardians_count }}
                                                    @else
                                                        <span class="text-danger" title="Belum ada wali"><i
                                                                class="fas fa-user-slash"></i></span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($s->trashed())
                                                        <span class="badge badge-danger">Terhapus</span>
                                                    @else
                                                        <span
                                                            class="badge badge-{{ $s->is_active ? 'success' : 'secondary' }}">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                    @endif
                                                    @if ($s->is_boarding)
                                                        <span class="badge badge-info">Asrama</span>
                                                    @endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.students.show', $s->id) }}"
                                                        class="btn btn-sm btn-outline-primary" title="Detail"><i
                                                            class="fas fa-eye"></i></a>
                                                    @unless ($s->trashed())
                                                        {!! $actionBtn(
                                                            route('superadmin.school.students.destroy', $s->id),
                                                            'Hapus Murid ' . $s->name,
                                                            'fa-trash',
                                                            'DELETE',
                                                            'danger',
                                                            'Murid dihapus sementara (soft delete). Histori absen, nilai & tagihan tetap ada dan murid bisa dipulihkan.',
                                                        ) !!}
                                                    @endunless
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Tidak ada murid.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>

                                {{-- ---------- WALI MURID ---------- --}}
                            @elseif ($tab === 'guardians')
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Wali</th>
                                            <th>Sekolah</th>
                                            <th>Kontak</th>
                                            <th>Anak</th>
                                            <th>Akun</th>
                                            <th class="text-right" style="width:60px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $u)
                                            @php $links = $children[$u->id] ?? collect(); @endphp
                                            <tr>
                                                <td>
                                                    @if ($userUrl($u->id))
                                                        <a href="{{ $userUrl($u->id) }}"
                                                            class="font-weight-bold">{{ $u->name }}</a>
                                                    @else
                                                        <span class="font-weight-bold">{{ $u->name }}</span>
                                                    @endif
                                                    <div class="text-small text-muted">Terdaftar
                                                        {{ $fmtDate($u->created_at) }}</div>
                                                </td>
                                                <td>{{ $companyNames[$u->company_id] ?? '#' . $u->company_id }}</td>
                                                <td>
                                                    <div>{{ $u->email }}</div>
                                                    <div class="text-small text-muted">{{ $u->phone ?: '-' }}</div>
                                                </td>
                                                <td>
                                                    @forelse ($links as $l)
                                                        <div>
                                                            @if ($l->student)
                                                                <a href="{{ route('superadmin.school.students.show', $l->student->id) }}"
                                                                    class="{{ $l->student->trashed() ? 'text-muted' : '' }}">{{ $l->student->name }}</a>
                                                                <span class="text-small text-muted">·
                                                                    {{ $relationships[$l->relationship] ?? $l->relationship }}{{ $l->student->classRoom ? ' · ' . $l->student->classRoom->name : '' }}</span>
                                                                @if ($l->is_primary)
                                                                    <span class="badge badge-light">utama</span>
                                                                @endif
                                                                @if ($l->student->trashed())
                                                                    <span class="badge badge-danger">murid terhapus</span>
                                                                @endif
                                                            @else
                                                                <span class="text-muted">Murid #{{ $l->student_id }} tidak
                                                                    ditemukan</span>
                                                            @endif
                                                        </div>
                                                    @empty
                                                        <span class="text-warning"><i class="fas fa-unlink"></i> Belum
                                                            terhubung</span>
                                                    @endforelse
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $u->is_active ? 'success' : 'secondary' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                </td>
                                                <td class="text-right">
                                                    @if ($userUrl($u->id))
                                                        <a href="{{ $userUrl($u->id) }}"
                                                            class="btn btn-sm btn-outline-primary" title="Kelola akun"><i
                                                                class="fas fa-user-cog"></i></a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Tidak ada akun
                                                    wali.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                <div class="px-4 py-2 text-small text-muted border-top">
                                    Reset password, nonaktifkan, atau hapus akun wali dilakukan di menu <b>Pengguna</b>.
                                    Untuk melepas hubungan wali dari murid, buka detail murid.
                                </div>

                                {{-- ---------- GURU KELAS ---------- --}}
                            @else
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Guru</th>
                                            <th>Kelas</th>
                                            <th>Sekolah</th>
                                            <th>Peran</th>
                                            <th>Mapel</th>
                                            <th>Sejak</th>
                                            <th class="text-right" style="width:60px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $r)
                                            <tr>
                                                <td>
                                                    @if ($r->teacher)
                                                        @if ($userUrl($r->teacher->id))
                                                            <a
                                                                href="{{ $userUrl($r->teacher->id) }}">{{ $r->teacher->name }}</a>
                                                        @else
                                                            {{ $r->teacher->name }}
                                                        @endif
                                                        @unless ($r->teacher->is_active)
                                                            <span class="badge badge-light">nonaktif</span>
                                                        @endunless
                                                        @if ($r->teacher->role !== 'guru')
                                                            <span class="badge badge-warning"
                                                                title="Akun ini bukan role guru">role:
                                                                {{ $r->teacher->role }}</span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">User #{{ $r->user_id }} tidak
                                                            ditemukan</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($r->classRoom)
                                                        <a
                                                            href="{{ route('superadmin.school.classes.show', $r->classRoom->id) }}">{{ $r->classRoom->name }}</a>
                                                        <div class="text-small text-muted">
                                                            {{ $r->classRoom->academic_year }}{{ $r->classRoom->is_active ? '' : ' · arsip' }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $companyNames[$r->classRoom->company_id ?? 0] ?? '-' }}</td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $r->role_in_class === 'wali_kelas' ? 'primary' : 'light' }}">{{ $roleLabels[$r->role_in_class] ?? $r->role_in_class }}</span>
                                                    @if (
                                                        $r->role_in_class === 'wali_kelas' &&
                                                            $r->classRoom &&
                                                            (int) $r->classRoom->homeroom_teacher_id !== (int) $r->user_id)
                                                        <div class="text-small text-warning"
                                                            title="Kolom wali kelas di data kelas menunjuk guru lain">tidak
                                                            sinkron</div>
                                                    @endif
                                                </td>
                                                <td>{{ $r->subject ?: '-' }}</td>
                                                <td>{{ $fmtDate($r->created_at) }}</td>
                                                <td class="text-right">
                                                    {!! $actionBtn(
                                                        route('superadmin.school.class-teachers.destroy', $r->id),
                                                        'Lepas ' . ($r->teacher->name ?? 'guru') . ' dari ' . ($r->classRoom->name ?? 'kelas'),
                                                        'fa-unlink',
                                                        'DELETE',
                                                        'danger',
                                                        'Akun guru tidak dihapus, hanya penugasan di kelas ini.',
                                                    ) !!}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Tidak ada penugasan
                                                    guru.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            @endif
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
