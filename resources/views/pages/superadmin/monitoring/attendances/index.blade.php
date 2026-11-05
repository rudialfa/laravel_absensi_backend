@extends('layouts.app')

@section('title', 'Monitoring Absensi')

@section('main')
    @php
        $isSingleDay = $from->isSameDay($to);
        $filterKeys = ['company_id', 'user_id', 'status', 'flag', 'search', 'from', 'to'];
        $hasFilter = collect(request()->only(['company_id', 'user_id', 'status', 'flag', 'search']))
            ->filter()
            ->isNotEmpty();
        $flags = [
            'outside' => 'Di luar radius',
            'no_checkout' => 'Belum absen pulang',
            'manual' => 'Diinput manual HR',
            'permission' => 'Terkait izin',
        ];
        $quick = [
            'Hari ini' => [today(), today()],
            'Kemarin' => [today()->subDay(), today()->subDay()],
            '7 hari' => [today()->subDays(6), today()],
            'Bulan ini' => [today()->startOfMonth(), today()],
            'Bulan lalu' => [
                today()->subMonthNoOverflow()->startOfMonth(),
                today()->subMonthNoOverflow()->endOfMonth(),
            ],
        ];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Monitoring Absensi</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring</div>
                    <div class="breadcrumb-item">Absensi</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="d-flex flex-wrap justify-content-between align-items-end mb-3">
                    <div>
                        <h2 class="section-title mt-0">Absensi Karyawan</h2>
                        <p class="section-lead mb-0">
                            {{ $isSingleDay ? $from->translatedFormat('l, d F Y') : $from->format('d M Y') . ' – ' . $to->format('d M Y') }}
                            · Data diinput karyawan & HR, superadmin hanya memantau.
                        </p>
                    </div>
                    <div class="mt-2">
                        @foreach ($quick as $label => [$qFrom, $qTo])
                            @php $active = $from->isSameDay($qFrom) && $to->isSameDay($qTo); @endphp
                            <a href="{{ route('superadmin.monitoring.attendances.index', array_merge(request()->only(['company_id', 'user_id', 'status', 'flag', 'search']), ['from' => $qFrom->toDateString(), 'to' => $qTo->toDateString()])) }}"
                                class="btn btn-sm mb-1 {{ $active ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>

                {{-- Ringkasan --}}
                <div class="row">
                    @foreach ([['Total Absen', $summary->total ?? 0, 'primary', 'fa-clipboard-check', null], ['Tepat Waktu', $summary->on_time ?? 0, 'success', 'fa-check', ['status' => 'on_time']], ['Terlambat', $summary->late ?? 0, 'warning', 'fa-clock', ['status' => 'late']], ['Belum Pulang', $summary->no_checkout ?? 0, 'secondary', 'fa-sign-out-alt', ['flag' => 'no_checkout']], ['Di Luar Radius', $summary->outside ?? 0, 'danger', 'fa-map-marker-alt', ['flag' => 'outside']], ['Input Manual HR', $summary->manual ?? 0, 'info', 'fa-user-edit', ['flag' => 'manual']]] as [$label, $value, $color, $icon, $filter])
                        <div class="col-6 col-md-4 col-xl-2">
                            @if ($filter)
                                <a href="{{ route('superadmin.monitoring.attendances.index', array_merge(request()->only(['company_id', 'user_id', 'search', 'from', 'to']), $filter)) }}"
                                    class="text-decoration-none">
                            @endif
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-{{ $color }}" style="width:56px"><i
                                        class="fas {{ $icon }}"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4 style="font-size:12px">{{ $label }}</h4>
                                    </div>
                                    <div class="card-body" style="font-size:18px">{{ number_format((int) $value) }}</div>
                                </div>
                            </div>
                            @if ($filter)
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Rekap per tenant (1 hari) --}}
                @if ($tenantRecap->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h4>Rekap per Perusahaan · {{ $from->format('d M Y') }}</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="pl-4">Perusahaan</th>
                                            <th class="text-center">Karyawan</th>
                                            <th class="text-center">Sudah Masuk</th>
                                            <th class="text-center">Terlambat</th>
                                            <th class="text-center">Sudah Pulang</th>
                                            <th class="text-center">Belum Absen</th>
                                            <th style="width:160px">Kehadiran</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($tenantRecap as $row)
                                            <tr>
                                                <td class="pl-4">
                                                    <a
                                                        href="{{ route('superadmin.monitoring.attendances.index', ['company_id' => $row->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $row->name }}</a>
                                                    @unless ($row->is_active)
                                                        <span class="badge badge-danger">suspend</span>
                                                    @endunless
                                                </td>
                                                <td class="text-center">{{ $row->employees }}</td>
                                                <td class="text-center">{{ $row->checkin }}</td>
                                                <td
                                                    class="text-center {{ $row->late ? 'text-warning font-weight-bold' : '' }}">
                                                    {{ $row->late }}</td>
                                                <td class="text-center">{{ $row->checkout }}</td>
                                                <td class="text-center {{ $row->absent ? 'text-danger' : '' }}">
                                                    {{ $row->absent }}</td>
                                                <td>
                                                    @if ($row->rate !== null)
                                                        <div class="progress" style="height:6px">
                                                            <div class="progress-bar bg-{{ $row->rate >= 80 ? 'success' : ($row->rate >= 50 ? 'warning' : 'danger') }}"
                                                                style="width: {{ $row->rate }}%"></div>
                                                        </div>
                                                        <small class="text-muted">{{ $row->rate }}%</small>
                                                    @else
                                                        <small class="text-muted">belum ada karyawan</small>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.monitoring.attendances.index') }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="this.form.user_id && (this.form.user_id.value=''); this.form.submit()">
                                        <option value="">Semua Perusahaan</option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>
                                                {{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($employees->isNotEmpty())
                                    <div class="col-md-2 mb-3">
                                        <select name="user_id" class="form-control">
                                            <option value="">Semua Karyawan</option>
                                            @foreach ($employees as $emp)
                                                <option value="{{ $emp->id }}" @selected((string) request('user_id') === (string) $emp->id)>
                                                    {{ $emp->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <div class="col-md-2 mb-3">
                                        <input type="text" name="search" value="{{ request('search') }}"
                                            class="form-control" placeholder="Nama karyawan">
                                    </div>
                                @endif
                                <div class="col-md-2 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected(request('status') === $value)>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="flag" class="form-control">
                                        <option value="">Semua Kondisi</option>
                                        @foreach ($flags as $value => $label)
                                            <option value="{{ $value }}" @selected(request('flag') === $value)>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-1 mb-3">
                                    <input type="date" name="from" value="{{ $from->toDateString() }}"
                                        class="form-control px-1" title="Dari">
                                </div>
                                <div class="col-md-1 mb-3">
                                    <input type="date" name="to" value="{{ $to->toDateString() }}"
                                        class="form-control px-1" title="Sampai">
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block" title="Cari"><i
                                            class="fas fa-search"></i></button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    @if ($hasFilter)
                                        <a href="{{ route('superadmin.monitoring.attendances.index', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                                            class="text-small"><i class="fas fa-times"></i> Hapus filter</a>
                                    @endif
                                </div>
                                <a href="{{ route('superadmin.monitoring.attendances.export', request()->only($filterKeys) + ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                                    class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> Unduh CSV</a>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Karyawan</th>
                                        <th>Masuk</th>
                                        <th>Pulang</th>
                                        <th>Status</th>
                                        <th class="text-center">Foto</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($attendances as $a)
                                        <tr>
                                            <td class="text-small">
                                                {{ optional($a->date)->translatedFormat('D, d M Y') }}
                                                @if ($a->shift)
                                                    <div class="text-muted">{{ $a->shift->name }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>{{ $a->user->name ?? '(user terhapus)' }}</strong>
                                                <div class="text-small text-muted">
                                                    {{ $a->company->name ?? '-' }}{{ $a->user && $a->user->department ? ' · ' . $a->user->department : '' }}
                                                </div>
                                            </td>
                                            <td>
                                                {{ $a->time_in ? substr($a->time_in, 0, 5) : '-' }}
                                                @if ($a->scheduled_in)
                                                    <div class="text-small text-muted">jadwal
                                                        {{ substr($a->scheduled_in, 0, 5) }}</div>
                                                @endif
                                                @if ($a->is_outside_radius_in)
                                                    <div class="text-small text-danger"><i
                                                            class="fas fa-map-marker-alt"></i> luar
                                                        radius{{ $a->distance_in_m ? ' (' . number_format($a->distance_in_m) . ' m)' : '' }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $a->time_out ? substr($a->time_out, 0, 5) : '-' }}
                                                @if ($a->early_leave_minutes)
                                                    <div class="text-small text-warning">pulang cepat
                                                        {{ $a->early_leave_minutes }} mnt</div>
                                                @endif
                                                @if ($a->is_outside_radius_out)
                                                    <div class="text-small text-danger"><i
                                                            class="fas fa-map-marker-alt"></i> luar radius</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span
                                                    class="badge badge-{{ $badge[$a->status] ?? 'secondary' }}">{{ $statuses[$a->status] ?? ucfirst((string) $a->status) }}</span>
                                                @if ($a->late_minutes)
                                                    <div class="text-small text-warning">telat {{ $a->late_minutes }} mnt
                                                    </div>
                                                @endif
                                                @if ($a->marked_by)
                                                    <div class="text-small text-info"><i class="fas fa-user-edit"></i>
                                                        input HR</div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($a->photo_in)
                                                    <img src="{{ asset($a->photo_in) }}" alt=""
                                                        class="rounded border"
                                                        style="width:36px;height:36px;object-fit:cover" loading="lazy">
                                                @endif
                                                @if ($a->photo_out)
                                                    <img src="{{ asset($a->photo_out) }}" alt=""
                                                        class="rounded border"
                                                        style="width:36px;height:36px;object-fit:cover" loading="lazy">
                                                @endif
                                                @if (!$a->photo_in && !$a->photo_out)
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.monitoring.attendances.show', $a->id) }}"
                                                    class="btn btn-sm btn-info" title="Detail"><i
                                                        class="fas fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">Tidak ada data absen
                                                pada periode ini</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($attendances->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">Menampilkan
                                {{ $attendances->firstItem() }}–{{ $attendances->lastItem() }} dari
                                {{ $attendances->total() }}</small>
                            {{ $attendances->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
