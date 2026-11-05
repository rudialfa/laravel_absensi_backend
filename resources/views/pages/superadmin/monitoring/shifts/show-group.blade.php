@extends('layouts.app')

@section('title', 'Grup ' . $group->name)

@section('main')
    @php
        $hm = fn($t) => $t ? substr($t, 0, 5) : '-';
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $isActivePeriod = fn($start, $end) => $start &&
            \Carbon\Carbon::parse($start)->lte(today()) &&
            (!$end || \Carbon\Carbon::parse($end)->gte(today()));
        $current = $group->assignments->first(fn($a) => $isActivePeriod($a->start_date, $a->end_date));
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.monitoring.shifts.index', ['tab' => 'groups', 'company_id' => $group->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Grup Shift</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.monitoring.shifts.index', ['tab' => 'groups']) }}">Grup Shift</a>
                    </div>
                    <div class="breadcrumb-item">{{ $group->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $group->name }}</h4>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">{{ $group->description ?: 'Tanpa deskripsi' }}</p>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Perusahaan</th>
                                        <td class="text-right">
                                            @if ($company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $company->id) }}">{{ $company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Anggota</th>
                                        <td class="text-right">{{ $group->users->count() }}</td>
                                    </tr>
                                    <tr>
                                        <th>Shift sekarang</th>
                                        <td class="text-right">
                                            @if ($current && $current->shift)
                                                <a
                                                    href="{{ route('superadmin.monitoring.shifts.show', ['shifts', $current->shift_id]) }}">{{ $current->shift->name }}</a>
                                                <div class="text-small text-muted">
                                                    {{ $hm($current->shift->start_time) }}–{{ $hm($current->shift->end_time) }}
                                                </div>
                                            @else
                                                <span class="text-muted">Tidak ada</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer bg-whitesmoke">
                                @if (!$current)
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                        data-target="#shiftDeleteModal"
                                        data-action="{{ route('superadmin.monitoring.shifts.destroy', ['groups', $group->id]) }}"
                                        data-label="grup {{ $group->name }}">
                                        <i class="fas fa-trash"></i> Hapus Grup
                                    </button>
                                @else
                                    <small class="text-muted d-block text-center">Grup masih punya penugasan yang
                                        berjalan.</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Anggota ({{ $group->users->count() }})</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="pl-4">Nama</th>
                                            <th>Departemen</th>
                                            <th>Periode Keanggotaan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($group->users as $u)
                                            <tr>
                                                <td class="pl-4">{{ $u->name }} @unless ($u->is_active)
                                                        <span class="badge badge-secondary">nonaktif</span>
                                                    @endunless
                                                </td>
                                                <td>{{ $u->department ?: '-' }}</td>
                                                <td class="text-small">
                                                    {{ $u->pivot->start_date ? $fmtDate($u->pivot->start_date) : 'awal' }}
                                                    –
                                                    {{ $u->pivot->end_date ? $fmtDate($u->pivot->end_date) : 'seterusnya' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Belum ada anggota</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Penugasan Shift</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="pl-4">Shift</th>
                                            <th>Periode</th>
                                            <th>Dibuat oleh</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($group->assignments as $as)
                                            <tr>
                                                <td class="pl-4">{{ $as->shift->name ?? '-' }} <span
                                                        class="text-small text-muted">{{ $as->shift ? $hm($as->shift->start_time) . '–' . $hm($as->shift->end_time) : '' }}</span>
                                                </td>
                                                <td>
                                                    {{ $fmtDate($as->start_date) }} –
                                                    {{ $as->end_date ? $fmtDate($as->end_date) : 'seterusnya' }}
                                                    @if ($isActivePeriod($as->start_date, $as->end_date))
                                                        <span class="badge badge-success">berlaku</span>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $as->creator->name ?? '-' }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-toggle="modal" data-target="#shiftDeleteModal"
                                                        data-action="{{ route('superadmin.monitoring.shifts.destroy', ['assignments', $as->id]) }}"
                                                        data-label="penugasan grup">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">Belum pernah ditugaskan
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.monitoring.shifts._delete')
@endsection
