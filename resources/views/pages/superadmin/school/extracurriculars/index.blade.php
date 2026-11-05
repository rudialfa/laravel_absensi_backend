@extends('layouts.app')

@section('title', 'Ekskul & Prestasi')

@section('main')
    @php
        $Ctrl = \App\Http\Controllers\Web\SuperAdmin\School\SuperAdminExtracurricularController::class;
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $hm = fn($t) => $t ? substr($t, 0, 5) : '';
        $tabUrl = fn($key, $extra = []) => route(
            'superadmin.school.extracurriculars.index',
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
        $delBtn = fn(
            $tabKey,
            $id,
            $label,
            $note = '',
        ) => '<button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" data-toggle="modal" data-target="#schoolActionModal"' .
            ' data-action="' .
            e(route('superadmin.school.extracurriculars.destroy', [$tabKey, $id])) .
            '" data-label="' .
            e('Hapus ' . $label) .
            '" data-note="' .
            e($note) .
            '">
<i class="fas fa-trash"></i></button>';
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Ekskul &amp; Prestasi</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring Sekolah</div>
                    <div class="breadcrumb-item">Ekskul &amp; Prestasi</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">Kegiatan ekstrakurikuler (anggota &amp; absensi) dan prestasi murid di semua sekolah.
                </p>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ $tabUrl($key) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>

                @if ($tab === 'extracurriculars')
                    <div class="row">
                        @foreach ([['Ekskul Aktif', $summary['active'] . ' / ' . $summary['total'], 'primary', 'fa-futbol', ['status' => 'active']], ['Anggota Aktif', number_format($summary['members']), 'info', 'fa-users', null], ['Tanpa Pembina', $summary['no_pembina'], $summary['no_pembina'] ? 'warning' : 'success', 'fa-user-times', ['status' => 'no_pembina']], ['Tidak Ada Kegiatan 30 Hari', $summary['idle'], $summary['idle'] ? 'danger' : 'success', 'fa-calendar-times', ['status' => 'idle']]] as [$label, $value, $color, $icon, $filter])
                            <div class="col-6 col-lg-3">
                                @if ($filter)
                                    <a href="{{ $tabUrl('extracurriculars', $filter) }}" class="text-decoration-none">
                                @endif
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
                                @if ($filter)
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="row">
                        @foreach ($levels as $v => $l)
                            <div class="col-6 col-md-4 col-xl-2">
                                <a href="{{ $tabUrl('achievements', request()->only(['extracurricular_id', 'year', 'search']) + ['level' => $v]) }}"
                                    class="text-decoration-none">
                                    <div class="card mb-3 {{ request('level') === $v ? 'border border-primary' : '' }}">
                                        <div class="card-body py-3 text-center">
                                            <div class="h4 mb-0">{{ number_format((int) ($byLevel[$v] ?? 0)) }}</div>
                                            <div class="text-small text-muted">{{ $l }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.school.extracurriculars.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="['extracurricular_id','class_id'].forEach(n => this.form[n] && (this.form[n].value='')); this.form.submit()">
                                        <option value="">Semua Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($tab === 'extracurriculars')
                                    <div class="col-md-2 mb-3">
                                        <select name="hari" class="form-control">
                                            <option value="">Semua Hari</option>
                                            @foreach ($days as $v => $l)
                                                <option value="{{ $v }}" @selected(request('hari') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua</option>
                                            <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                                            <option value="no_pembina" @selected(request('status') === 'no_pembina')>Belum ada pembina
                                            </option>
                                            <option value="idle" @selected(request('status') === 'idle')>Tidak ada kegiatan 30 hari
                                            </option>
                                        </select>
                                    </div>
                                @else
                                    <div class="col-md-2 mb-3">
                                        <select name="extracurricular_id" class="form-control" @disabled($ekskulOptions->isEmpty())>
                                            <option value="">
                                                {{ $ekskulOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Ekskul' }}
                                            </option>
                                            @foreach ($ekskulOptions as $e)
                                                <option value="{{ $e->id }}" @selected((string) request('extracurricular_id') === (string) $e->id)>
                                                    {{ $e->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="class_id" class="form-control" @disabled($classOptions->isEmpty())>
                                            <option value="">
                                                {{ $classOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Kelas' }}
                                            </option>
                                            @foreach ($classOptions as $c)
                                                <option value="{{ $c->id }}" @selected((string) request('class_id') === (string) $c->id)>
                                                    {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1 mb-3">
                                        <select name="year" class="form-control">
                                            <option value="">Tahun</option>
                                            @for ($y = now()->year; $y >= now()->year - 5; $y--)
                                                <option value="{{ $y }}" @selected((string) request('year') === (string) $y)>
                                                    {{ $y }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control"
                                        placeholder="{{ $tab === 'extracurriculars' ? 'Nama ekskul / pembina / murid' : 'Judul prestasi / nama / NIS' }}">
                                </div>
                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.school.extracurriculars.export', request()->query() + ['tab' => $tab]) }}"
                                        class="btn btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.school.extracurriculars.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                @if ($tab === 'extracurriculars')
                                    <thead>
                                        <tr>
                                            <th>Ekskul</th>
                                            <th>Sekolah</th>
                                            <th>Pembina</th>
                                            <th>Jadwal</th>
                                            <th class="text-center">Anggota</th>
                                            <th style="width:170px">Kehadiran 30 Hari</th>
                                            <th>Terakhir</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $e)
                                            @php $rate = $e->att_30 ? round($e->hadir_30 / $e->att_30 * 100) : null; @endphp
                                            <tr class="{{ $e->is_active ? '' : 'text-muted' }}">
                                                <td>
                                                    <a href="{{ route('superadmin.school.extracurriculars.show', $e->id) }}"
                                                        class="font-weight-bold">{{ $e->name }}</a>
                                                    @unless ($e->is_active)
                                                        <span class="badge badge-secondary">nonaktif</span>
                                                    @endunless
                                                    @if ($e->achievements_count)
                                                        <div class="text-small text-warning"><i class="fas fa-trophy"></i>
                                                            {{ $e->achievements_count }} prestasi</div>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $companyNames[$e->company_id] ?? '-' }}</td>
                                                <td>
                                                    @if ($e->pembina)
                                                        {{ $e->pembina->name }}
                                                        @unless ($e->pembina->is_active)
                                                            <span class="badge badge-light">nonaktif</span>
                                                        @endunless
                                                        @if ((int) $e->pembina->company_id !== (int) $e->company_id)
                                                            <span class="badge badge-danger">beda sekolah</span>
                                                        @endif
                                                    @else
                                                        <span class="text-warning text-small">Belum ada</span>
                                                    @endif
                                                </td>
                                                <td class="text-small text-nowrap">{{ $days[$e->hari] ?? '-' }}<div
                                                        class="text-muted">
                                                        {{ $e->jam_mulai ? $hm($e->jam_mulai) . '–' . $hm($e->jam_selesai) : '' }}
                                                    </div>
                                                </td>
                                                <td class="text-center">{{ $e->active_members_count }}</td>
                                                <td>
                                                    @if ($rate !== null)
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-fill mr-2" style="height:8px">
                                                                <div class="progress-bar bg-{{ $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger') }}"
                                                                    style="width:{{ $rate }}%"></div>
                                                            </div>
                                                            <span class="text-small">{{ $rate }}%</span>
                                                        </div>
                                                        <div class="text-small text-muted">{{ $e->sessions_30 }} pertemuan
                                                        </div>
                                                    @else
                                                        <span class="text-muted text-small">Belum ada absensi</span>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $fmtDate($e->last_session) }}</td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.extracurriculars.show', $e->id) }}"
                                                        class="btn btn-sm btn-outline-primary"><i
                                                            class="fas fa-eye"></i></a>
                                                    {!! $delBtn(
                                                        'extracurriculars',
                                                        $e->id,
                                                        'ekskul ' . $e->name,
                                                        'Semua anggota & data absensi ekskul ini ikut terhapus. Prestasi murid tetap ada.',
                                                    ) !!}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Belum ada ekskul.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                @else
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Murid</th>
                                            <th>Prestasi</th>
                                            <th>Tingkat</th>
                                            <th>Ekskul</th>
                                            <th>Sertifikat</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $a)
                                            <tr>
                                                <td class="text-nowrap">{{ $fmtDate($a->achieved_at) }}</td>
                                                <td>
                                                    @if ($a->student)
                                                        @if ($studentUrl($a->student->id))
                                                            <a
                                                                href="{{ $studentUrl($a->student->id) }}">{{ $a->student->name }}</a>
                                                        @else
                                                            {{ $a->student->name }}
                                                        @endif
                                                        <div class="text-small text-muted">
                                                            {{ $a->student->classRoom->name ?? '' }} ·
                                                            {{ $companyNames[$a->company_id] ?? '' }}</div>
                                                    @endif
                                                </td>
                                                <td><b>{{ $a->title }}</b>
                                                    @if ($a->rank)
                                                        <div class="text-small"><i class="fas fa-medal text-warning"></i>
                                                            {{ $a->rank }}</div>
                                                    @endif
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $levelBadge[$a->level] ?? 'light' }}">{{ $levels[$a->level] ?? $a->level }}</span>
                                                </td>
                                                <td class="text-small">{{ $a->extracurricular->name ?? '-' }}</td>
                                                <td>
                                                    @if ($a->certificate_path)
                                                        <a href="{{ $Ctrl::fileUrl($a->certificate_path) }}"
                                                            target="_blank" class="text-small"><i
                                                                class="fas fa-certificate"></i> Lihat</a>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-right">{!! $delBtn('achievements', $a->id, 'prestasi ' . $a->title, 'Sertifikat ikut terhapus.') !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Belum ada prestasi
                                                    tercatat.</td>
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
