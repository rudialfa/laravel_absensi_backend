@extends('layouts.app')

@section('title', 'Kelas ' . $class->name)

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $statusBadge = [
            'hadir' => 'success',
            'terlambat' => 'warning',
            'izin' => 'info',
            'sakit' => 'primary',
            'alpa' => 'danger',
        ];
        $stVal = fn($s) => $s instanceof \BackedEnum ? $s->value : $s;
        $userUrl = fn($id) => Route::has('superadmin.users.show') ? route('superadmin.users.show', $id) : null;
        $deviceUrl = fn($id) => Route::has('superadmin.school.devices.show')
            ? route('superadmin.school.devices.show', $id)
            : null;
        $activeStudents = $students->where('is_active', true);
        $recapTotal = $recap->sum();
        $presentRate = $recapTotal
            ? round(((($recap['hadir'] ?? 0) + ($recap['terlambat'] ?? 0)) / $recapTotal) * 100, 1)
            : null;
        $todayRecorded = $activeStudents->filter(fn($s) => $s->todayAttendance)->count();
        $used = array_filter($dependents);
        $depLabels = [
            'students' => 'murid',
            'student_attendances' => 'absen',
            'lesson_schedules' => 'jadwal',
            'grades' => 'nilai',
            'assignments' => 'tugas',
            'learning_materials' => 'materi',
            'academic_events' => 'kalender',
            'attendance_devices' => 'kiosk',
        ];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.classes.index', ['tab' => 'classes', 'company_id' => $class->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Kelas {{ $class->name }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.school.classes.index') }}">Kelas, Murid &amp;
                            Wali</a></div>
                    <div class="breadcrumb-item">{{ $class->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    @foreach ([['Murid Aktif', $activeStudents->count(), 'primary', 'fa-user-graduate'], ['Absen Hari Ini', $todayRecorded . ' / ' . $activeStudents->count(), $todayRecorded ? 'success' : 'secondary', 'fa-clipboard-check'], ['Kehadiran 30 Hari', $presentRate !== null ? $presentRate . '%' : '-', 'info', 'fa-chart-line'], ['Murid Tanpa Wali', $activeStudents->where('guardians_count', 0)->count(), $activeStudents->where('guardians_count', 0)->count() ? 'danger' : 'success', 'fa-user-slash']] as [$label, $value, $color, $icon])
                        <div class="col-6 col-lg-3">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></div>
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

                <div class="row">
                    <div class="col-12 col-lg-8">
                        {{-- Daftar murid --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Murid ({{ $students->count() }})</h4>
                                <div class="card-header-action">
                                    <a href="{{ route('superadmin.school.classes.export', ['tab' => 'students', 'company_id' => $class->company_id, 'class_id' => $class->id]) }}"
                                        class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> CSV</a>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:40px">#</th>
                                                <th>Nama</th>
                                                <th>NIS</th>
                                                <th>L/P</th>
                                                <th class="text-center">Wali</th>
                                                <th>Hari Ini</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($students as $i => $s)
                                                @php
                                                    $att = $s->todayAttendance;
                                                    $st = $att ? $stVal($att->status) : null;
                                                @endphp
                                                <tr class="{{ $s->is_active ? '' : 'text-muted' }}">
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>
                                                        <a
                                                            href="{{ route('superadmin.school.students.show', $s->id) }}">{{ $s->name }}</a>
                                                        @if ($s->is_boarding)
                                                            <span class="badge badge-info">Asrama</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $s->nis }}</td>
                                                    <td>{{ $s->gender }}</td>
                                                    <td class="text-center">
                                                        @if ($s->guardians_count)
                                                            {{ $s->guardians_count }}
                                                        @else
                                                            <span class="text-danger" title="Belum ada wali"><i
                                                                    class="fas fa-user-slash"></i></span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($att)
                                                            <span
                                                                class="badge badge-{{ $statusBadge[$st] ?? 'light' }}">{{ $statuses[$st] ?? $st }}</span>
                                                            @if ($att->check_in_time)
                                                                <span
                                                                    class="text-small text-muted">{{ substr($att->check_in_time, 0, 5) }}</span>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td><span
                                                            class="badge badge-{{ $s->is_active ? 'success' : 'secondary' }}">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">Belum ada murid
                                                        di kelas ini.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- Guru --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Guru di Kelas Ini</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Guru</th>
                                            <th>Peran</th>
                                            <th>Mapel</th>
                                            <th>Sejak</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($class->classTeachers as $ct)
                                            <tr>
                                                <td>
                                                    @if ($ct->teacher)
                                                        @if ($userUrl($ct->teacher->id))
                                                            <a
                                                                href="{{ $userUrl($ct->teacher->id) }}">{{ $ct->teacher->name }}</a>
                                                        @else
                                                            {{ $ct->teacher->name }}
                                                        @endif
                                                        <div class="text-small text-muted">{{ $ct->teacher->email }}</div>
                                                    @else
                                                        <span class="text-muted">User #{{ $ct->user_id }}</span>
                                                    @endif
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $ct->role_in_class === 'wali_kelas' ? 'primary' : 'light' }}">{{ $roleLabels[$ct->role_in_class] ?? $ct->role_in_class }}</span>
                                                </td>
                                                <td>{{ $ct->subject ?: '-' }}</td>
                                                <td>{{ $fmtDate($ct->created_at) }}</td>
                                                <td class="text-right">
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-toggle="modal" data-target="#schoolActionModal"
                                                        data-action="{{ route('superadmin.school.class-teachers.destroy', $ct->id) }}"
                                                        data-label="Lepas {{ $ct->teacher->name ?? 'guru' }} dari kelas {{ $class->name }}"
                                                        data-note="Akun guru tidak dihapus, hanya penugasan di kelas ini."
                                                        data-button="Lepas"><i class="fas fa-unlink"></i></button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">Belum ada guru yang
                                                    ditugaskan.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Info Kelas</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $class->is_active ? 'success' : 'secondary' }}">{{ $class->is_active ? 'Aktif' : 'Arsip' }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Sekolah</th>
                                        <td class="text-right">
                                            @if ($class->company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $class->company->id) }}">{{ $class->company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Tingkat</th>
                                        <td class="text-right">{{ $class->grade_level }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tahun ajaran</th>
                                        <td class="text-right">{{ $class->academic_year }}</td>
                                    </tr>
                                    <tr>
                                        <th>Wali kelas</th>
                                        <td class="text-right">
                                            @if ($class->homeroomTeacher)
                                                {{ $class->homeroomTeacher->name }}
                                                @if ($class->homeroomTeacher->phone)
                                                    <div class="text-small text-muted">{{ $class->homeroomTeacher->phone }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-warning">Belum ada</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Riwayat kenaikan</th>
                                        <td class="text-right">{{ $promotions }} data</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($class->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Absen 30 Hari Terakhir</h4>
                            </div>
                            <div class="card-body">
                                @if ($recapTotal)
                                    @foreach ($statuses as $value => $label)
                                        @php $n = (int) ($recap[$value] ?? 0); @endphp
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><span
                                                    class="badge badge-{{ $statusBadge[$value] ?? 'light' }}">&nbsp;</span>
                                                {{ $label }}</span>
                                            <span>{{ $n }} <span
                                                    class="text-muted text-small">({{ round(($n / $recapTotal) * 100) }}%)</span></span>
                                        </div>
                                    @endforeach
                                    <div class="text-small text-muted mt-2">{{ $activeDays }} hari ada catatan absen ·
                                        {{ $recapTotal }} catatan</div>
                                @else
                                    <p class="text-muted mb-0">Belum ada absen dalam 30 hari terakhir.</p>
                                @endif
                                @if (Route::has('superadmin.school.attendances.index'))
                                    <a href="{{ route('superadmin.school.attendances.index', ['company_id' => $class->company_id, 'class_id' => $class->id]) }}"
                                        class="btn btn-sm btn-light btn-block mt-3">Lihat semua absen kelas</a>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Perangkat Kiosk</h4>
                            </div>
                            <div class="card-body">
                                @forelse ($class->attendanceDevices as $d)
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            @if ($deviceUrl($d->id))
                                                <a href="{{ $deviceUrl($d->id) }}">{{ $d->name }}</a>
                                            @else
                                                {{ $d->name }}
                                            @endif
                                            <div class="text-small text-muted">Terakhir aktif
                                                {{ $d->last_seen_at ? $d->last_seen_at->diffForHumans() : 'belum pernah' }}
                                            </div>
                                        </div>
                                        <span
                                            class="badge badge-{{ $d->is_active ? 'success' : 'secondary' }}">{{ $d->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Tidak ada kiosk khusus kelas ini (bisa memakai kiosk umum
                                        sekolah).</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="card border border-warning">
                            <div class="card-body">
                                <h6 class="text-warning mb-1">
                                    {{ $class->is_active ? 'Arsipkan Kelas' : 'Aktifkan Kelas' }}</h6>
                                <p class="small text-muted">
                                    {{ $class->is_active ? 'Untuk kelas tahun ajaran lama. Murid & histori tetap ada.' : 'Kelas akan muncul lagi sebagai kelas aktif di aplikasi.' }}
                                </p>
                                <button type="button" class="btn btn-outline-warning btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal"
                                    data-action="{{ route('superadmin.school.classes.toggle-active', $class->id) }}"
                                    data-method="PATCH"
                                    data-label="{{ $class->is_active ? 'Arsipkan' : 'Aktifkan' }} Kelas {{ $class->name }}"
                                    data-button="{{ $class->is_active ? 'Arsipkan' : 'Aktifkan' }}">
                                    <i class="fas {{ $class->is_active ? 'fa-archive' : 'fa-box-open' }}"></i>
                                    {{ $class->is_active ? 'Arsipkan' : 'Aktifkan' }}
                                </button>
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Kelas</h6>
                                @if ($used)
                                    <p class="small text-muted mb-0">
                                        Tidak bisa dihapus karena masih dipakai:
                                        {{ collect($used)->map(fn($n, $t) => $n . ' ' . ($depLabels[$t] ?? $t))->implode(', ') }}.
                                        Gunakan <b>Arsipkan</b>.
                                    </p>
                                @else
                                    <p class="small text-muted">Kelas ini kosong dan bisa dihapus (misalnya dibuat ganda).
                                    </p>
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                        data-target="#schoolActionModal"
                                        data-action="{{ route('superadmin.school.classes.destroy', $class->id) }}"
                                        data-label="Hapus Kelas {{ $class->name }}"><i class="fas fa-trash"></i>
                                        Hapus</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
