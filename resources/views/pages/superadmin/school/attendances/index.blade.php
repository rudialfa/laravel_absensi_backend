@extends('layouts.app')

@section('title', 'Absen & Izin Murid')

@section('main')
    @php
        $Ctrl = \App\Http\Controllers\Web\SuperAdmin\School\SuperAdminStudentAttendanceController::class;
        $stVal = fn($s) => $s instanceof \BackedEnum ? $s->value : $s;
        $dateQ = ['date_from' => request('date_from'), 'date_to' => request('date_to')];
        $tabUrl = fn($key, $extra = []) => route(
            'superadmin.school.attendances.index',
            array_filter(
                ['tab' => $key, 'company_id' => request('company_id')] + $dateQ + $extra,
                fn($v) => $v !== null && $v !== '',
            ),
        );
        $hasFilter = collect(request()->except(['tab', 'page']))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();
        $rangeLabel = $from->equalTo($to)
            ? $from->translatedFormat('d M Y')
            : $from->translatedFormat('d M Y') . ' – ' . $to->translatedFormat('d M Y');
        $studentUrl = fn($id) => Route::has('superadmin.school.students.show')
            ? route('superadmin.school.students.show', $id)
            : null;
        $classUrl = fn($id) => Route::has('superadmin.school.classes.show')
            ? route('superadmin.school.classes.show', $id)
            : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Absen &amp; Izin Murid</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring Sekolah</div>
                    <div class="breadcrumb-item">Absen &amp; Izin Murid</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Absen murid dari kiosk dan guru, serta pengajuan izin/sakit dari wali, untuk semua sekolah.
                    Periode: <b>{{ $rangeLabel }}</b>.
                </p>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ $tabUrl($key) }}">
                                {{ $label }}
                                @if ($key === 'permissions' && $tab === 'permissions' && $summary->pending)
                                    <span class="badge badge-warning">{{ $summary->pending }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{-- ================= RINGKASAN ================= --}}
                @if ($tab === 'attendances')
                    @php $totalAll = $byStatus->sum(); @endphp
                    <div class="row">
                        @foreach ($statuses as $value => $label)
                            @php $n = (int) ($byStatus[$value] ?? 0); @endphp
                            <div class="col-6 col-md">
                                <a href="{{ $tabUrl('attendances', request()->only(['class_id', 'source', 'device_id', 'search']) + ['status' => $value]) }}"
                                    class="text-decoration-none">
                                    <div
                                        class="card card-statistic-1 {{ request('status') === $value ? 'border border-' . ($badge[$value] ?? 'light') : '' }}">
                                        <div class="card-icon bg-{{ $badge[$value] ?? 'light' }}"
                                            style="width:60px;height:60px;line-height:60px;margin:10px"><span
                                                class="text-white font-weight-bold">{{ $totalAll ? round(($n / $totalAll) * 100) : 0 }}%</span>
                                        </div>
                                        <div class="card-wrap">
                                            <div class="card-header">
                                                <h4>{{ \Illuminate\Support\Str::before($label, ' (') }}</h4>
                                            </div>
                                            <div class="card-body" style="font-size:18px">{{ number_format($n) }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @elseif ($tab === 'permissions')
                    <div class="row">
                        @foreach ([['Pengajuan', $summary->total, 'primary', 'fa-envelope-open-text', null], ['Menunggu', $summary->pending, $summary->pending ? 'warning' : 'success', 'fa-hourglass-half', 'pending'], ['Disetujui', $summary->approved, 'success', 'fa-check', 'approved'], ['Ditolak', $summary->rejected, 'danger', 'fa-times', 'rejected']] as [$label, $value, $color, $icon, $filter])
                            <div class="col-6 col-lg-3">
                                <a href="{{ $tabUrl('permissions', $filter ? ['status' => $filter] : []) }}"
                                    class="text-decoration-none">
                                    <div class="card card-statistic-1">
                                        <div class="card-icon bg-{{ $color }}"><i
                                                class="fas {{ $icon }}"></i></div>
                                        <div class="card-wrap">
                                            <div class="card-header">
                                                <h4>{{ $label }}</h4>
                                            </div>
                                            <div class="card-body" style="font-size:18px">{{ number_format((int) $value) }}
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                    @if ($stale)
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Ada <b>{{ $stale }}</b> pengajuan izin yang tanggalnya sudah lewat tapi belum diproses
                            sekolah.
                            <a href="{{ route('superadmin.school.attendances.index', array_filter(['tab' => 'permissions', 'company_id' => request('company_id'), 'status' => 'stale', 'date_from' => now()->subYear()->toDateString()])) }}"
                                class="alert-link">Lihat</a>
                        </div>
                    @endif
                @endif

                <div class="card">
                    {{-- ================= FILTER ================= --}}
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.school.attendances.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="if(this.form.class_id){this.form.class_id.value=''} if(this.form.device_id){this.form.device_id.value=''} this.form.submit()">
                                        <option value="">Semua Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <input type="date" name="date_from" value="{{ $from->toDateString() }}"
                                        class="form-control" title="Dari tanggal">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <input type="date" name="date_to" value="{{ $to->toDateString() }}"
                                        class="form-control" title="Sampai tanggal">
                                </div>

                                @if ($tab !== 'recap')
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
                                    <div class="col-md mb-3">
                                        <input type="text" name="search" value="{{ request('search') }}"
                                            class="form-control" placeholder="Nama / NIS murid">
                                    </div>
                                @endif

                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.school.attendances.export', request()->query() + ['tab' => $tab, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}"
                                        class="btn btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>

                            @if ($tab === 'attendances')
                                <div class="form-row">
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control form-control-sm">
                                            <option value="">Semua Status</option>
                                            @foreach ($statuses as $v => $l)
                                                <option value="{{ $v }}" @selected(request('status') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="source" class="form-control form-control-sm">
                                            <option value="">Kiosk &amp; Manual</option>
                                            <option value="kiosk" @selected(request('source') === 'kiosk')>Lewat kiosk</option>
                                            <option value="manual" @selected(request('source') === 'manual')>Input manual guru</option>
                                        </select>
                                    </div>
                                    @if ($devices->isNotEmpty())
                                        <div class="col-md-2 mb-3">
                                            <select name="device_id" class="form-control form-control-sm">
                                                <option value="">Semua Kiosk</option>
                                                @foreach ($devices as $d)
                                                    <option value="{{ $d->id }}" @selected((string) request('device_id') === (string) $d->id)>
                                                        {{ $d->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    <div class="col-md-2 mb-3">
                                        <select name="photo" class="form-control form-control-sm">
                                            <option value="">Foto: semua</option>
                                            <option value="1" @selected(request('photo') === '1')>Ada foto</option>
                                            <option value="0" @selected(request('photo') === '0')>Tanpa foto</option>
                                        </select>
                                    </div>
                                </div>
                            @elseif ($tab === 'permissions')
                                <div class="form-row">
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control form-control-sm">
                                            <option value="">Semua Status</option>
                                            @foreach ($permStatuses as $v => $l)
                                                <option value="{{ $v }}" @selected(request('status') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                            <option value="stale" @selected(request('status') === 'stale')>Menunggu &amp; sudah lewat
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="type" class="form-control form-control-sm">
                                            <option value="">Izin &amp; Sakit</option>
                                            @foreach ($permTypes as $v => $l)
                                                <option value="{{ $v }}" @selected(request('type') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="attachment" class="form-control form-control-sm">
                                            <option value="">Lampiran: semua</option>
                                            <option value="1" @selected(request('attachment') === '1')>Ada lampiran</option>
                                        </select>
                                    </div>
                                </div>
                            @endif

                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.school.attendances.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">

                            {{-- ---------- ABSEN HARIAN ---------- --}}
                            @if ($tab === 'attendances')
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Murid</th>
                                            <th>Kelas</th>
                                            <th>Sekolah</th>
                                            <th>Status</th>
                                            <th>Sumber</th>
                                            <th class="text-center">Foto</th>
                                            <th class="text-right" style="width:90px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $a)
                                            @php $st = $stVal($a->status); @endphp
                                            <tr>
                                                <td>
                                                    {{ $a->date ? $a->date->translatedFormat('D, d M Y') : '-' }}
                                                    <div class="text-small text-muted">
                                                        {{ $a->check_in_time ? substr($a->check_in_time, 0, 5) : '' }}
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($a->student)
                                                        @if ($studentUrl($a->student->id))
                                                            <a
                                                                href="{{ $studentUrl($a->student->id) }}">{{ $a->student->name }}</a>
                                                        @else
                                                            {{ $a->student->name }}
                                                        @endif
                                                        <div class="text-small text-muted">NIS {{ $a->student->nis }}
                                                            @if ($a->student->trashed())
                                                                · <span class="text-danger">terhapus</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>{{ $a->classRoom->name ?? '-' }}</td>
                                                <td class="text-small">
                                                    {{ $companyNames[$a->company_id] ?? '#' . $a->company_id }}</td>
                                                <td><span
                                                        class="badge badge-{{ $badge[$st] ?? 'light' }}">{{ \Illuminate\Support\Str::before($statuses[$st] ?? $st, ' (') }}</span>
                                                </td>
                                                <td class="text-small">
                                                    @if ($a->device)
                                                        <i class="fas fa-tablet-alt text-muted"></i>
                                                        {{ $a->device->name }}
                                                    @elseif ($a->recordedBy)
                                                        <i class="fas fa-user text-muted"></i> {{ $a->recordedBy->name }}
                                                    @else
                                                        <span
                                                            class="text-muted">{{ $a->notes ? \Illuminate\Support\Str::limit($a->notes, 30) : '-' }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($a->photo_evidence)
                                                        <i class="fas fa-camera text-success" title="Ada foto"></i>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.attendances.show', ['attendances', $a->id]) }}"
                                                        class="btn btn-sm btn-outline-primary" title="Detail"><i
                                                            class="fas fa-eye"></i></a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        title="Hapus" data-toggle="modal"
                                                        data-target="#schoolActionModal"
                                                        data-action="{{ route('superadmin.school.attendances.destroy', ['attendances', $a->id]) }}"
                                                        data-label="Hapus absen {{ $a->student->name ?? '' }} ({{ optional($a->date)->format('d M Y') }})"
                                                        data-note="Foto bukti ikut terhapus. Murid bisa diabsen ulang untuk tanggal ini."><i
                                                            class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Tidak ada data
                                                    absen pada periode ini.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>

                                {{-- ---------- REKAP ---------- --}}
                            @elseif ($tab === 'recap')
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ $perClass ? 'Kelas' : 'Sekolah' }}</th>
                                            <th class="text-center">Murid Aktif</th>
                                            <th class="text-center">Hari Tercatat</th>
                                            <th class="text-center">Hadir</th>
                                            <th class="text-center">Terlambat</th>
                                            <th class="text-center">Izin</th>
                                            <th class="text-center">Sakit</th>
                                            <th class="text-center">Alpa</th>
                                            <th style="width:160px">Kehadiran</th>
                                            <th class="text-center">Hari Ini</th>
                                            <th>Absen Terakhir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recap as $r)
                                            @php
                                                $rateColor =
                                                    $r['rate'] === null
                                                        ? 'secondary'
                                                        : ($r['rate'] >= 90
                                                            ? 'success'
                                                            : ($r['rate'] >= 75
                                                                ? 'warning'
                                                                : 'danger'));
                                                $coverage = $schoolDays ? round(($r['days'] / $schoolDays) * 100) : 0;
                                            @endphp
                                            <tr>
                                                <td>
                                                    @if ($perClass)
                                                        @if ($classUrl($r['id']))
                                                            <a href="{{ $classUrl($r['id']) }}">{{ $r['name'] }}</a>
                                                        @else
                                                            {{ $r['name'] }}
                                                        @endif
                                                    @else
                                                        <a
                                                            href="{{ $tabUrl('recap', ['company_id' => $r['id']]) }}">{{ $r['name'] }}</a>
                                                        @if (isset($r['is_active']) && !$r['is_active'])
                                                            <span class="badge badge-danger">suspend</span>
                                                        @endif
                                                    @endif
                                                    <div class="text-small text-muted">{{ $r['sub'] }}</div>
                                                </td>
                                                <td class="text-center">{{ $r['students'] }}</td>
                                                <td class="text-center">
                                                    {{ $r['days'] }} <span class="text-small text-muted">/
                                                        {{ $schoolDays }}</span>
                                                    @if ($r['students'] && $coverage < 50)
                                                        <div><span class="badge badge-warning"
                                                                title="Kurang dari separuh hari sekolah ada catatan absen">jarang
                                                                absen</span></div>
                                                    @endif
                                                </td>
                                                <td class="text-center text-success">{{ $r['hadir'] }}</td>
                                                <td class="text-center text-warning">{{ $r['terlambat'] }}</td>
                                                <td class="text-center text-info">{{ $r['izin'] }}</td>
                                                <td class="text-center text-primary">{{ $r['sakit'] }}</td>
                                                <td class="text-center text-danger">{{ $r['alpa'] }}</td>
                                                <td>
                                                    @if ($r['rate'] !== null)
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-fill mr-2" style="height:8px">
                                                                <div class="progress-bar bg-{{ $rateColor }}"
                                                                    style="width:{{ $r['rate'] }}%"></div>
                                                            </div>
                                                            <span class="text-small">{{ $r['rate'] }}%</span>
                                                        </div>
                                                        <div class="text-small text-muted">
                                                            {{ $r['total'] ? round(($r['via_kiosk'] / $r['total']) * 100) : 0 }}%
                                                            lewat kiosk</div>
                                                    @else
                                                        <span class="text-muted">Belum ada absen</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($r['students'])
                                                        <span
                                                            class="{{ $r['today'] ? '' : 'text-danger' }}">{{ $r['today'] }}/{{ $r['students'] }}</span>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="text-small">
                                                    {{ $r['last_date'] ? \Carbon\Carbon::parse($r['last_date'])->format('d M Y') : '-' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="11" class="text-center text-muted py-4">Tidak ada data.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                <div class="px-4 py-2 text-small text-muted border-top">
                                    Kehadiran = (hadir + terlambat) ÷ semua catatan absen. Hari tercatat dibandingkan dengan
                                    hari Senin–Sabtu dalam periode (belum memperhitungkan hari libur).
                                    @unless ($perClass)
                                        Klik nama sekolah untuk melihat rekap per kelas.
                                    @endunless
                                </div>

                                {{-- ---------- IZIN / SAKIT ---------- --}}
                            @else
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tanggal Izin</th>
                                            <th>Murid</th>
                                            <th>Sekolah</th>
                                            <th>Jenis</th>
                                            <th>Alasan</th>
                                            <th>Diajukan</th>
                                            <th>Status</th>
                                            <th class="text-right" style="width:90px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $p)
                                            @php $late = $p->status === 'pending' && $p->date_permission && $p->date_permission->lt(today()); @endphp
                                            <tr>
                                                <td>{{ $p->date_permission ? $p->date_permission->translatedFormat('D, d M Y') : '-' }}
                                                </td>
                                                <td>
                                                    @if ($p->student)
                                                        @if ($studentUrl($p->student->id))
                                                            <a
                                                                href="{{ $studentUrl($p->student->id) }}">{{ $p->student->name }}</a>
                                                        @else
                                                            {{ $p->student->name }}
                                                        @endif
                                                        <div class="text-small text-muted">
                                                            {{ $p->student->classRoom->name ?? 'tanpa kelas' }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-small">
                                                    {{ $companyNames[$p->student->company_id ?? 0] ?? '-' }}</td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $p->type === 'sakit' ? 'primary' : 'info' }}">{{ $permTypes[$p->type] ?? $p->type }}</span>
                                                    @if ($p->attachment)
                                                        <i class="fas fa-paperclip text-muted" title="Ada lampiran"></i>
                                                    @endif
                                                </td>
                                                <td class="text-small">
                                                    {{ \Illuminate\Support\Str::limit($p->reason, 60) }}</td>
                                                <td class="text-small">
                                                    {{ $p->submittedBy->name ?? '-' }}
                                                    <div class="text-muted">
                                                        {{ optional($p->created_at)->format('d M H:i') }}</div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $permBadge[$p->status] ?? 'light' }}">{{ $permStatuses[$p->status] ?? $p->status }}</span>
                                                    @if ($late)
                                                        <div class="text-small text-danger">sudah lewat</div>
                                                    @endif
                                                    @if ($p->reviewedBy)
                                                        <div class="text-small text-muted">{{ $p->reviewedBy->name }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.attendances.show', ['permissions', $p->id]) }}"
                                                        class="btn btn-sm btn-outline-primary" title="Detail"><i
                                                            class="fas fa-eye"></i></a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        title="Hapus" data-toggle="modal"
                                                        data-target="#schoolActionModal"
                                                        data-action="{{ route('superadmin.school.attendances.destroy', ['permissions', $p->id]) }}"
                                                        data-label="Hapus izin {{ $p->student->name ?? '' }} ({{ optional($p->date_permission)->format('d M Y') }})"
                                                        data-note="Lampiran ikut terhapus. Absen yang sudah tercatat dari izin ini tidak ikut terhapus."><i
                                                            class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Tidak ada pengajuan
                                                    izin pada periode ini.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>

                    @if ($tab !== 'recap' && $items->hasPages())
                        <div class="card-footer text-right">{{ $items->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
