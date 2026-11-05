@extends('layouts.app')

@section('title', 'Shift & Jadwal')

@section('main')
    @php
        $hm = fn($t) => $t ? substr($t, 0, 5) : '-';
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $attStatus = \App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminAttendanceController::STATUSES;
        $attBadge = \App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminAttendanceController::STATUS_BADGE;
        $isActivePeriod = fn($start, $end) => $start &&
            \Carbon\Carbon::parse($start)->lte(today()) &&
            (!$end || \Carbon\Carbon::parse($end)->gte(today()));
        $delBtn = fn(
            $type,
            $id,
            $label,
        ) => '<button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" data-toggle="modal" data-target="#shiftDeleteModal" data-action="' .
            e(route('superadmin.monitoring.shifts.destroy', [$type, $id])) .
            '" data-label="' .
            e($label) .
            '"><i class="fas fa-trash"></i></button>';
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Shift &amp; Jadwal</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring</div>
                    <div class="breadcrumb-item">Shift &amp; Jadwal</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Pengaturan shift dan jadwal di semua perusahaan. Urutan penentuan shift karyawan:
                    <b>shift khusus</b> → <b>grup shift</b> → <b>shift default</b> → jam kerja perusahaan.
                </p>

                {{-- Filter perusahaan (berlaku di semua tab) --}}
                <form method="GET" action="{{ route('superadmin.monitoring.shifts.index') }}" class="mb-3">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="form-row align-items-center">
                        <div class="col-md-4 mb-2">
                            <select name="company_id" class="form-control" onchange="this.form.submit()">
                                <option value="">
                                    {{ $tab === 'roster' ? '-- Pilih perusahaan --' : 'Semua Perusahaan' }}</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if ($tab === 'roster')
                            <div class="col-md-3 mb-2">
                                <input type="date" name="date" value="{{ $rosterDate->toDateString() }}"
                                    class="form-control" onchange="this.form.submit()">
                            </div>
                        @elseif (in_array($tab, ['shifts', 'groups', 'overrides', 'schedules']))
                            <div class="col-md-3 mb-2">
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                    placeholder="{{ $tab === 'overrides' ? 'Nama karyawan' : ($tab === 'schedules' ? 'Judul jadwal' : 'Nama') }}">
                            </div>
                        @endif
                        @if ($tab === 'schedules')
                            <div class="col-md-2 mb-2">
                                <select name="status" class="form-control">
                                    <option value="">Semua Status</option>
                                    @foreach ($scheduleStatus as $value => $label)
                                        <option value="{{ $value }}" @selected(request('status') === $value)>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if ($tab === 'overrides')
                            <div class="col-md-2 mb-2">
                                <select name="status" class="form-control">
                                    <option value="">Semua Status</option>
                                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                    <option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option>
                                </select>
                            </div>
                        @endif
                        @if ($tab === 'assignments')
                            <div class="col-md-3 mb-2">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="active" value="1" class="custom-control-input"
                                        id="only-active" @checked(request('active')) onchange="this.form.submit()">
                                    <label class="custom-control-label" for="only-active">Hanya yang berlaku hari
                                        ini</label>
                                </div>
                            </div>
                        @endif
                        @if ($tab !== 'roster')
                            <div class="col-md-1 mb-2">
                                <button class="btn btn-primary btn-block"><i class="fas fa-search"></i></button>
                            </div>
                        @endif
                    </div>
                </form>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ route('superadmin.monitoring.shifts.index', array_filter(['tab' => $key, 'company_id' => request('company_id')])) }}">
                                {{ $label }}
                                @isset($counts[$key])
                                    <span class="badge badge-light">{{ $counts[$key] }}</span>
                                @endisset
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="card">
                    {{-- ═════════ TAB: SHIFT HARI INI ═════════ --}}
                    @if ($tab === 'roster')
                        @if (!$rosterCompany)
                            <div class="card-body text-center text-muted py-5">
                                <i class="fas fa-building fa-2x mb-3"></i>
                                <p class="mb-0">Pilih perusahaan untuk melihat shift setiap karyawan pada tanggal
                                    tertentu.</p>
                            </div>
                        @else
                            <div class="card-header">
                                <h4>{{ $rosterCompany->name }} · {{ $rosterDate->translatedFormat('l, d F Y') }}</h4>
                            </div>
                            @if ($shiftSummary->isNotEmpty())
                                <div class="card-body pb-0">
                                    @foreach ($shiftSummary as $name => $n)
                                        <span class="badge badge-light mr-1 mb-1">{{ $name }}: {{ $n }}
                                            orang</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Karyawan</th>
                                                <th>Shift</th>
                                                <th>Jam</th>
                                                <th>Absen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($rows as $row)
                                                <tr>
                                                    <td>
                                                        {{ $row->user->name }}
                                                        <div class="text-small text-muted">
                                                            {{ $row->user->department ?: '-' }}</div>
                                                    </td>
                                                    <td>
                                                        @if ($row->shift)
                                                            <a
                                                                href="{{ route('superadmin.monitoring.shifts.show', ['shifts', $row->shift->id]) }}">{{ $row->shift->name }}</a>
                                                        @else
                                                            <span class="text-muted">Jam default perusahaan</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $row->start }} – {{ $row->end }}</td>
                                                    <td>
                                                        @if ($row->attendance)
                                                            <a
                                                                href="{{ route('superadmin.monitoring.attendances.show', $row->attendance->id) }}">
                                                                {{ $hm($row->attendance->time_in) }} –
                                                                {{ $hm($row->attendance->time_out) }}
                                                            </a>
                                                            <span
                                                                class="badge badge-{{ $attBadge[$row->attendance->status] ?? 'secondary' }}">{{ $attStatus[$row->attendance->status] ?? $row->attendance->status }}</span>
                                                        @else
                                                            <span class="text-muted">belum absen</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">Perusahaan ini
                                                        belum punya karyawan aktif</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- ═════════ TAB: DAFTAR SHIFT ═════════ --}}
                    @elseif ($tab === 'shifts')
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Shift</th>
                                            <th>Perusahaan</th>
                                            <th>Jam</th>
                                            <th>Toleransi</th>
                                            <th class="text-center">Dipakai</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $shift)
                                            @php $used = $shift->attendances_count + $shift->assignments_count + $shift->overrides_count; @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.monitoring.shifts.show', ['shifts', $shift->id]) }}"
                                                        class="font-weight-bold">{{ $shift->name }}</a>
                                                    @if ($shift->is_default)
                                                        <span class="badge badge-primary">Default</span>
                                                    @endif
                                                </td>
                                                <td>{{ $companyNames[$shift->company_id] ?? '-' }}</td>
                                                <td>{{ $hm($shift->start_time) }} – {{ $hm($shift->end_time) }}
                                                    @if ($shift->end_time && $shift->start_time && $shift->end_time <= $shift->start_time)
                                                        <span class="badge badge-info">malam</span>
                                                    @endif
                                                </td>
                                                <td>{{ (int) $shift->grace_period_minutes }} menit</td>
                                                <td class="text-center text-small">
                                                    {{ $shift->attendances_count }} absen<br>
                                                    {{ $shift->assignments_count }} penugasan ·
                                                    {{ $shift->overrides_count }} khusus
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('superadmin.monitoring.shifts.show', ['shifts', $shift->id]) }}"
                                                        class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                                    @if ($used === 0)
                                                        {!! $delBtn('shifts', $shift->id, 'shift ' . $shift->name) !!}
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Belum ada shift</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- ═════════ TAB: GRUP ═════════ --}}
                    @elseif ($tab === 'groups')
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Grup</th>
                                            <th>Perusahaan</th>
                                            <th class="text-center">Anggota</th>
                                            <th>Shift Berlaku Sekarang</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $group)
                                            @php $current = $group->assignments->first(fn($a) => $isActivePeriod($a->start_date, $a->end_date)); @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.monitoring.shifts.show', ['groups', $group->id]) }}"
                                                        class="font-weight-bold">{{ $group->name }}</a>
                                                    @if ($group->description)
                                                        <div class="text-small text-muted">
                                                            {{ \Illuminate\Support\Str::limit($group->description, 60) }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $companyNames[$group->company_id] ?? '-' }}</td>
                                                <td class="text-center">{{ $group->users_count }}</td>
                                                <td>
                                                    @if ($current && $current->shift)
                                                        {{ $current->shift->name }}
                                                        ({{ $hm($current->shift->start_time) }}–{{ $hm($current->shift->end_time) }})
                                                        <div class="text-small text-muted">sejak
                                                            {{ $fmtDate($current->start_date) }}</div>
                                                    @else
                                                        <span class="text-muted">Tidak ada</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('superadmin.monitoring.shifts.show', ['groups', $group->id]) }}"
                                                        class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                                    @unless ($current)
                                                        {!! $delBtn('groups', $group->id, 'grup ' . $group->name) !!}
                                                    @endunless
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">Belum ada grup
                                                    shift</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- ═════════ TAB: PENUGASAN ═════════ --}}
                    @elseif ($tab === 'assignments')
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Grup</th>
                                            <th>Shift</th>
                                            <th>Periode</th>
                                            <th>Rotasi</th>
                                            <th>Dibuat oleh</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $as)
                                            <tr>
                                                <td>
                                                    @if ($as->group)
                                                        <a
                                                            href="{{ route('superadmin.monitoring.shifts.show', ['groups', $as->shift_group_id]) }}">{{ $as->group->name }}</a>
                                                    @else
                                                        <span class="text-muted">(grup terhapus)</span>
                                                    @endif
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$as->company_id] ?? '-' }}</div>
                                                </td>
                                                <td>{{ $as->shift->name ?? '-' }} <span
                                                        class="text-small text-muted">{{ $as->shift ? $hm($as->shift->start_time) . '–' . $hm($as->shift->end_time) : '' }}</span>
                                                </td>
                                                <td>
                                                    {{ $fmtDate($as->start_date) }} –
                                                    {{ $as->end_date ? $fmtDate($as->end_date) : 'seterusnya' }}
                                                    @if ($isActivePeriod($as->start_date, $as->end_date))
                                                        <span class="badge badge-success">berlaku</span>
                                                    @endif
                                                </td>
                                                <td>{{ $as->is_rotation ? 'Tiap ' . $as->rotation_cycle_days . ' hari' : '-' }}
                                                </td>
                                                <td class="text-small">
                                                    {{ $as->creator->name ?? '-' }}<br>{{ optional($as->created_at)->format('d M Y') }}
                                                </td>
                                                <td class="text-center">{!! $delBtn('assignments', $as->id, 'penugasan grup') !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Belum ada penugasan
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- ═════════ TAB: SHIFT KHUSUS ═════════ --}}
                    @elseif ($tab === 'overrides')
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Karyawan</th>
                                            <th>Shift</th>
                                            <th>Periode</th>
                                            <th>Alasan</th>
                                            <th>Status</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $ov)
                                            <tr>
                                                <td>
                                                    {{ $ov->user->name ?? '-' }}
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$ov->company_id] ?? '-' }}{{ $ov->user && $ov->user->department ? ' · ' . $ov->user->department : '' }}
                                                    </div>
                                                </td>
                                                <td>{{ $ov->shift->name ?? '-' }} <span
                                                        class="text-small text-muted">{{ $ov->shift ? $hm($ov->shift->start_time) . '–' . $hm($ov->shift->end_time) : '' }}</span>
                                                </td>
                                                <td>
                                                    {{ $fmtDate($ov->start_date) }} –
                                                    {{ $ov->end_date ? $fmtDate($ov->end_date) : 'seterusnya' }}
                                                    @if ($ov->status === 'active' && $isActivePeriod($ov->start_date, $ov->end_date))
                                                        <span class="badge badge-success">berlaku</span>
                                                    @endif
                                                </td>
                                                <td class="text-small" style="max-width:220px">
                                                    {{ \Illuminate\Support\Str::limit($ov->reason, 60) ?: '-' }}</td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $ov->status === 'active' ? 'success' : 'secondary' }}">{{ $ov->status === 'active' ? 'Aktif' : 'Dibatalkan' }}</span>
                                                    <div class="text-small text-muted">oleh
                                                        {{ $ov->creator->name ?? '-' }}</div>
                                                </td>
                                                <td class="text-center">{!! $delBtn('overrides', $ov->id, 'shift khusus') !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Belum ada shift
                                                    khusus</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- ═════════ TAB: JADWAL ═════════ --}}
                    @elseif ($tab === 'schedules')
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Jadwal</th>
                                            <th>Waktu</th>
                                            <th>Untuk</th>
                                            <th class="text-center">Peserta</th>
                                            <th>Status</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $sc)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.monitoring.shifts.show', ['schedules', $sc->id]) }}"
                                                        class="font-weight-bold">{{ $sc->title }}</a>
                                                    <div class="text-small text-muted">
                                                        {{ $scheduleTypes[$sc->type] ?? $sc->type }} ·
                                                        {{ $companyNames[$sc->company_id] ?? '-' }}</div>
                                                </td>
                                                <td class="text-small">
                                                    {{ optional($sc->start_datetime)->format('d M Y H:i') }}
                                                    @if ($sc->end_datetime)
                                                        <br>s/d
                                                        {{ $sc->end_datetime->format($sc->end_datetime->isSameDay($sc->start_datetime) ? 'H:i' : 'd M Y H:i') }}
                                                    @endif
                                                    @if ($sc->is_recurring)
                                                        <br><span class="badge badge-light">berulang</span>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $sc->user->name ?? '-' }}<br><span
                                                        class="text-muted">dibuat {{ $sc->creator->name ?? '-' }}</span>
                                                </td>
                                                <td class="text-center">{{ $sc->participants_count }}</td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ ['upcoming' => 'info', 'done' => 'success', 'canceled' => 'secondary'][$sc->status] ?? 'light' }}">
                                                        {{ $scheduleStatus[$sc->status] ?? $sc->status }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('superadmin.monitoring.shifts.show', ['schedules', $sc->id]) }}"
                                                        class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                                    {!! $delBtn('schedules', $sc->id, 'jadwal ' . $sc->title) !!}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Belum ada jadwal
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if (isset($items) && $items->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">Menampilkan {{ $items->firstItem() }}–{{ $items->lastItem() }} dari
                                {{ $items->total() }}</small>
                            {{ $items->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.monitoring.shifts._delete')
@endsection
