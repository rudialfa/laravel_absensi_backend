@extends('layouts.app')

@section('title', 'Ekskul ' . $ekskul->name)

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $hm = fn($t) => $t ? substr($t, 0, 5) : '';
        $studentUrl = fn($id) => Route::has('superadmin.school.students.show')
            ? route('superadmin.school.students.show', $id)
            : null;
        $activeMembers = $members->where('is_active', true);
        $totalRecords = $memberRecap->sum('total');
        $totalHadir = $memberRecap->sum('hadir');
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.extracurriculars.index', ['company_id' => $ekskul->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $ekskul->name }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.school.extracurriculars.index') }}">Ekskul</a></div>
                    <div class="breadcrumb-item">{{ $ekskul->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    @foreach ([['Anggota Aktif', $activeMembers->count(), 'primary', 'fa-users'], ['Total Pertemuan', $totalSessions, 'info', 'fa-calendar-check'], ['Kehadiran', $totalRecords ? round(($totalHadir / $totalRecords) * 100) . '%' : '-', 'success', 'fa-chart-line'], ['Prestasi', $achievements->count(), 'warning', 'fa-trophy']] as [$label, $value, $color, $icon])
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
                        <div class="card">
                            <div class="card-header">
                                <h4>Absensi {{ $sessionDates->count() }} Pertemuan Terakhir</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 text-center">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Anggota</th>
                                                @foreach ($sessionDates as $d)
                                                    <th class="text-small" style="min-width:46px">
                                                        {{ \Carbon\Carbon::parse($d)->format('d/m') }}</th>
                                                @endforeach
                                                <th>Hadir</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($members as $m)
                                                @php
                                                    $row = $grid[$m->student_id] ?? collect();
                                                    $rec = $memberRecap[$m->student_id] ?? null;
                                                @endphp
                                                <tr class="{{ $m->is_active ? '' : 'text-muted' }}">
                                                    <td class="text-left text-nowrap">
                                                        @if ($m->student && $studentUrl($m->student->id))
                                                            <a
                                                                href="{{ $studentUrl($m->student->id) }}">{{ $m->student->name }}</a>
                                                        @else
                                                            {{ $m->student->name ?? '-' }}
                                                        @endif
                                                        <span
                                                            class="text-small text-muted">{{ $m->student->classRoom->name ?? '' }}</span>
                                                        @unless ($m->is_active)
                                                            <span class="badge badge-light">keluar</span>
                                                        @endunless
                                                    </td>
                                                    @foreach ($sessionDates as $d)
                                                        @php $st = $row[$d] ?? null; @endphp
                                                        <td>
                                                            @if ($st)
                                                                <span class="badge badge-{{ $attBadge[$st] ?? 'light' }}"
                                                                    title="{{ $attStatus[$st] ?? $st }}">{{ strtoupper(substr($st, 0, 1)) }}</span>
                                                            @else
                                                                <span class="text-muted">·</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="text-small">
                                                        {{ $rec ? (int) $rec->hadir . '/' . (int) $rec->total : '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="{{ $sessionDates->count() + 2 }}" class="text-muted py-3">
                                                        Belum ada anggota.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer text-small text-muted">
                                @foreach ($attStatus as $v => $l)
                                    <span
                                        class="badge badge-{{ $attBadge[$v] }}">{{ strtoupper(substr($v, 0, 1)) }}</span>
                                    {{ $l }} &nbsp;
                                @endforeach
                            </div>
                        </div>

                        @if ($achievements->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Prestasi dari Ekskul Ini</h4>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            @foreach ($achievements as $a)
                                                <tr>
                                                    <td>{{ $fmtDate($a->achieved_at) }}</td>
                                                    <td>{{ $a->student->name ?? '-' }}</td>
                                                    <td><b>{{ $a->title }}</b> {{ $a->rank ? '· ' . $a->rank : '' }}
                                                    </td>
                                                    <td><span
                                                            class="badge badge-{{ $levelBadge[$a->level] ?? 'light' }}">{{ $levels[$a->level] ?? $a->level }}</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Info Ekskul</h4>
                                <div class="card-header-action"><span
                                        class="badge badge-{{ $ekskul->is_active ? 'success' : 'secondary' }}">{{ $ekskul->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Sekolah</th>
                                        <td class="text-right">
                                            @if ($ekskul->company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $ekskul->company->id) }}">{{ $ekskul->company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Pembina</th>
                                        <td class="text-right">
                                            @if ($ekskul->pembina)
                                                {{ $ekskul->pembina->name }}
                                                <div class="text-small text-muted">
                                                    {{ $ekskul->pembina->phone ?: $ekskul->pembina->email }}</div>
                                                @if ((int) $ekskul->pembina->company_id !== (int) $ekskul->company_id)
                                                    <span class="badge badge-danger">beda sekolah</span>
                                                @endif
                                            @else
                                                <span class="text-warning">Belum ada</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Jadwal</th>
                                        <td class="text-right">{{ $days[$ekskul->hari] ?? '-' }}
                                            {{ $ekskul->jam_mulai ? $hm($ekskul->jam_mulai) . '–' . $hm($ekskul->jam_selesai) : '' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Anggota pernah ikut</th>
                                        <td class="text-right">{{ $members->count() }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($ekskul->created_at)->format('d M Y') }}</td>
                                    </tr>
                                </table>
                                @if ($ekskul->description)
                                    <hr>
                                    <div class="small">{!! nl2br(e($ekskul->description)) !!}</div>
                                @endif
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Ekskul</h6>
                                <p class="small text-muted">Semua anggota ({{ $members->count() }}) dan data absensi ekskul
                                    ini ikut terhapus. Prestasi murid tetap tersimpan.</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal"
                                    data-action="{{ route('superadmin.school.extracurriculars.destroy', ['extracurriculars', $ekskul->id]) }}"
                                    data-label="Hapus ekskul {{ $ekskul->name }}"><i class="fas fa-trash"></i>
                                    Hapus</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
