@extends('layouts.app')

@section('title', 'Murid ' . $student->name)

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
        $trashed = $student->trashed();
        $recapTotal = $recap->sum();
        $presentRate = $recapTotal
            ? round(((($recap['hadir'] ?? 0) + ($recap['terlambat'] ?? 0)) / $recapTotal) * 100, 1)
            : null;
        $room = $student->activeRoomAssignment;
        $health = $student->healthRecord;
        $age = $student->birth_date ? $student->birth_date->age : null;

        // Data terkait lain + link ke menu monitoring-nya (muncul kalau route sudah ada)
        $related = [
            [
                'Izin / Sakit',
                $student->permissions_count,
                'superadmin.school.attendances.index',
                ['tab' => 'permissions'],
            ],
            ['Nilai', $student->grades_count, 'superadmin.school.academics.index', ['tab' => 'grades']],
            ['Tagihan', $student->bills_count, 'superadmin.school.finance.index', ['tab' => 'bills']],
            ['Mutasi', $student->mutations_count, 'superadmin.school.ppdb.index', ['tab' => 'mutations']],
            [
                'Kenaikan Kelas',
                $student->class_promotions_count,
                'superadmin.school.academics.index',
                ['tab' => 'promotions'],
            ],
            ['Kunjungan UKS', $student->uks_visits_count, 'superadmin.school.health.index', ['tab' => 'visits']],
            ['Ekskul', $student->extracurricular_memberships_count, 'superadmin.school.extracurriculars.index', []],
            [
                'Prestasi',
                $student->achievements_count,
                'superadmin.school.extracurriculars.index',
                ['tab' => 'achievements'],
            ],
            [
                'Setoran Tahfidz',
                $student->tahfidz_setorans_count,
                'superadmin.boarding.mutabaah.index',
                ['tab' => 'tahfidz'],
            ],
            ['Izin Asrama', $student->boarding_permissions_count, 'superadmin.boarding.permissions.index', []],
        ];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.classes.index', ['tab' => 'students', 'company_id' => $student->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $student->name }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.school.classes.index', ['tab' => 'students']) }}">Murid</a></div>
                    <div class="breadcrumb-item">{{ $student->nis }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($trashed)
                    <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <i class="fas fa-trash"></i> Murid ini <b>dihapus</b> pada
                            {{ $student->deleted_at->format('d M Y H:i') }}.
                            Tidak muncul di aplikasi sekolah, tapi historinya masih ada.
                        </div>
                        <form action="{{ route('superadmin.school.students.restore', $student->id) }}" method="POST"
                            class="mt-2 mt-md-0"
                            onsubmit="return confirm('Pulihkan murid {{ addslashes($student->name) }}?')">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-light btn-sm"><i class="fas fa-trash-restore"></i> Pulihkan</button>
                        </form>
                    </div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-4">
                        {{-- Profil --}}
                        <div class="card profile-widget mt-5">
                            <div class="profile-widget-header">
                                @if ($photoUrl)
                                    <img alt="Foto" src="{{ $photoUrl }}"
                                        class="rounded-circle profile-widget-picture"
                                        style="width:100px;height:100px;object-fit:cover"
                                        onerror="this.onerror=null;this.src='{{ asset('img/avatar/avatar-1.png') }}'">
                                @else
                                    <img alt="Foto" src="{{ asset('img/avatar/avatar-1.png') }}"
                                        class="rounded-circle profile-widget-picture">
                                @endif
                                <div class="profile-widget-items">
                                    <div class="profile-widget-item">
                                        <div class="profile-widget-item-label">Kehadiran 30 hr</div>
                                        <div class="profile-widget-item-value">
                                            {{ $presentRate !== null ? $presentRate . '%' : '-' }}</div>
                                    </div>
                                    <div class="profile-widget-item">
                                        <div class="profile-widget-item-label">Total absen</div>
                                        <div class="profile-widget-item-value">{{ number_format($totalAttendances) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="profile-widget-description pb-0">
                                <div class="profile-widget-name">
                                    {{ $student->name }}
                                    <div class="text-muted d-inline font-weight-normal">
                                        <div class="slash"></div> {{ $student->classRoom->name ?? 'Belum ada kelas' }}
                                    </div>
                                </div>
                                <div class="mb-2">
                                    @if ($trashed)
                                        <span class="badge badge-danger">Terhapus</span>
                                    @else
                                        <span
                                            class="badge badge-{{ $student->is_active ? 'success' : 'secondary' }}">{{ $student->is_active ? 'Aktif' : 'Nonaktif / Lulus' }}</span>
                                    @endif
                                    @if ($student->is_boarding)
                                        <span class="badge badge-info">Tinggal di asrama</span>
                                    @endif
                                </div>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Sekolah</th>
                                        <td class="text-right">
                                            @if ($student->company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $student->company->id) }}">{{ $student->company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>NIS</th>
                                        <td class="text-right">{{ $student->nis }}</td>
                                    </tr>
                                    <tr>
                                        <th>NISN</th>
                                        <td class="text-right">{{ $student->nisn ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jenis kelamin</th>
                                        <td class="text-right">{{ $genders[$student->gender] ?? $student->gender }}</td>
                                    </tr>
                                    <tr>
                                        <th>TTL</th>
                                        <td class="text-right">{{ $student->birth_place ?: '-' }},
                                            {{ $fmtDate($student->birth_date) }}{{ $age !== null ? ' (' . $age . ' th)' : '' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Terdaftar</th>
                                        <td class="text-right">{{ $fmtDate($student->enrolled_at) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Alamat</th>
                                        <td class="text-right">{{ $student->address ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($student->created_at)->format('d M Y H:i') }}
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        {{-- Kelas --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Kelas</h4>
                            </div>
                            <div class="card-body">
                                @if ($student->classRoom)
                                    <a href="{{ route('superadmin.school.classes.show', $student->classRoom->id) }}"
                                        class="font-weight-bold">{{ $student->classRoom->name }}</a>
                                    <div class="text-muted">Tingkat {{ $student->classRoom->grade_level }} ·
                                        {{ $student->classRoom->academic_year }}</div>
                                    <div class="text-small mt-1">Wali kelas:
                                        {{ $student->classRoom->homeroomTeacher->name ?? 'belum ada' }}</div>
                                @else
                                    <p class="text-warning mb-0"><i class="fas fa-exclamation-triangle"></i> Murid belum
                                        ditempatkan di kelas. Murid tanpa kelas tidak bisa diabsen di kiosk.</p>
                                @endif
                            </div>
                        </div>

                        @if ($student->is_boarding || $room)
                            <div class="card">
                                <div class="card-header">
                                    <h4>Asrama</h4>
                                </div>
                                <div class="card-body">
                                    @if ($room && $room->room)
                                        <div class="font-weight-bold">{{ $room->room->dormitory->name ?? 'Asrama' }} ·
                                            {{ $room->room->name }}</div>
                                        <div class="text-muted text-small">Masuk {{ $fmtDate($room->moved_in_at) }}</div>
                                    @else
                                        <p class="text-warning mb-0">Murid asrama ini belum ditempatkan di kamar.</p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($health)
                            <div class="card">
                                <div class="card-header">
                                    <h4>Data Kesehatan</h4>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th>Gol. darah</th>
                                            <td class="text-right">{{ $health->blood_type ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tinggi / Berat</th>
                                            <td class="text-right">
                                                {{ $health->height_cm ? (float) $health->height_cm . ' cm' : '-' }} /
                                                {{ $health->weight_kg ? (float) $health->weight_kg . ' kg' : '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Alergi</th>
                                            <td class="text-right">{{ $health->allergies ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Penyakit</th>
                                            <td class="text-right">{{ $health->chronic_conditions ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Kontak darurat</th>
                                            <td class="text-right">
                                                {{ $health->emergency_contact_name ?: '-' }}{{ $health->emergency_contact_phone ? ' · ' . $health->emergency_contact_phone : '' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Dicatat</th>
                                            <td class="text-right">{{ $fmtDate($health->recorded_at) }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-8">
                        {{-- Wali --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Wali / Orang Tua</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Hubungan</th>
                                                <th>Kontak</th>
                                                <th>Hak</th>
                                                <th class="text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($student->studentGuardians as $link)
                                                @php $g = $link->guardian; @endphp
                                                <tr>
                                                    <td>
                                                        @if ($g)
                                                            @if ($userUrl($g->id))
                                                                <a href="{{ $userUrl($g->id) }}">{{ $g->name }}</a>
                                                            @else
                                                                {{ $g->name }}
                                                            @endif
                                                            @if ($link->is_primary)
                                                                <span class="badge badge-primary">Utama</span>
                                                            @endif
                                                            @unless ($g->is_active)
                                                                <span class="badge badge-light">akun nonaktif</span>
                                                            @endunless
                                                            @if ($g->role !== 'wali')
                                                                <span class="badge badge-warning"
                                                                    title="Akun ini bukan role wali">role:
                                                                    {{ $g->role }}</span>
                                                            @endif
                                                            @if ((int) $g->company_id !== (int) $student->company_id)
                                                                <span class="badge badge-danger"
                                                                    title="Akun wali dari tenant lain">beda sekolah</span>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">User #{{ $link->user_id }} tidak
                                                                ditemukan</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $relationships[$link->relationship] ?? $link->relationship }}
                                                    </td>
                                                    <td>
                                                        <div>{{ $g->phone ?? '-' }}</div>
                                                        <div class="text-small text-muted">{{ $g->email ?? '' }}</div>
                                                    </td>
                                                    <td class="text-small">
                                                        {{ $link->can_submit_permission ? 'Boleh ajukan izin' : 'Tidak bisa ajukan izin' }}
                                                    </td>
                                                    <td class="text-right">
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                            title="Lepas" data-toggle="modal"
                                                            data-target="#schoolActionModal"
                                                            data-action="{{ route('superadmin.school.students.guardians.destroy', [$student->id, $link->id]) }}"
                                                            data-label="Lepas wali {{ $g->name ?? '' }} dari {{ $student->name }}"
                                                            data-note="Akun wali tidak dihapus. Wali tidak bisa lagi melihat data anak ini di aplikasi."
                                                            data-button="Lepas"><i class="fas fa-unlink"></i></button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-danger py-3">
                                                        <i class="fas fa-user-slash"></i> Belum ada wali. Orang tua tidak
                                                        bisa memantau absen anak ini. Hubungkan wali lewat aplikasi Admin
                                                        Sekolah.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- Rekap absen --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Absen 30 Hari Terakhir</h4>
                                @if (Route::has('superadmin.school.attendances.index'))
                                    <div class="card-header-action">
                                        <a href="{{ route('superadmin.school.attendances.index', ['company_id' => $student->company_id, 'search' => $student->nis]) }}"
                                            class="btn btn-sm btn-light">Semua absen</a>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body pb-2">
                                <div class="row text-center">
                                    @foreach ($statuses as $value => $label)
                                        <div class="col mb-3">
                                            <div class="h4 mb-0 text-{{ $statusBadge[$value] ?? 'dark' }}">
                                                {{ (int) ($recap[$value] ?? 0) }}</div>
                                            <div class="text-small text-muted">
                                                {{ \Illuminate\Support\Str::before($label, ' (') }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Status</th>
                                                <th>Jam</th>
                                                <th>Kelas</th>
                                                <th>Sumber</th>
                                                <th>Catatan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($recentAttendances as $a)
                                                @php $st = $stVal($a->status); @endphp
                                                <tr>
                                                    <td>{{ $a->date ? $a->date->translatedFormat('D, d M Y') : '-' }}</td>
                                                    <td><span
                                                            class="badge badge-{{ $statusBadge[$st] ?? 'light' }}">{{ $statuses[$st] ?? $st }}</span>
                                                    </td>
                                                    <td>{{ $a->check_in_time ? substr($a->check_in_time, 0, 5) : '-' }}
                                                    </td>
                                                    <td>{{ $a->classRoom->name ?? '-' }}</td>
                                                    <td class="text-small">
                                                        @if ($a->device)
                                                            <i class="fas fa-tablet-alt"></i> {{ $a->device->name }}
                                                        @elseif ($a->recordedBy)
                                                            <i class="fas fa-user"></i> {{ $a->recordedBy->name }}
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-small">{{ $a->notes ?: '' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3">Belum ada
                                                        catatan absen.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- Data terkait --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Data Terkait</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @foreach ($related as [$label, $count, $route, $params])
                                        <div class="col-6 col-md-4 col-xl-3 mb-3">
                                            <div class="border rounded p-2 h-100">
                                                <div class="text-small text-muted">{{ $label }}</div>
                                                <div class="h5 mb-0">
                                                    @if ($count && Route::has($route))
                                                        <a
                                                            href="{{ route($route, $params + ['company_id' => $student->company_id, 'search' => $student->nis]) }}">{{ number_format($count) }}</a>
                                                    @else
                                                        {{ number_format((int) $count) }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        @unless ($trashed)
                            <div class="card border border-danger">
                                <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                                    <div class="mr-3">
                                        <h6 class="text-danger mb-1">Hapus Murid</h6>
                                        <p class="small text-muted mb-0">
                                            Untuk data ganda atau salah input. Ini <b>soft delete</b>: histori absen, nilai
                                            &amp; tagihan tetap ada dan murid bisa dipulihkan.
                                            Untuk murid lulus/pindah, gunakan fitur kenaikan kelas atau mutasi di aplikasi
                                            sekolah.
                                        </p>
                                    </div>
                                    <button type="button" class="btn btn-outline-danger mt-2 mt-md-0" data-toggle="modal"
                                        data-target="#schoolActionModal"
                                        data-action="{{ route('superadmin.school.students.destroy', $student->id) }}"
                                        data-label="Hapus Murid {{ $student->name }}"><i class="fas fa-trash"></i>
                                        Hapus</button>
                                </div>
                            </div>
                        @endunless
                    </div>
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
