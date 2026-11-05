@extends('layouts.app')

@section('title', 'Perangkat Kiosk')

@section('main')
    @php
        $Ctrl = \App\Http\Controllers\Web\SuperAdmin\School\SuperAdminDeviceController::class;
        $hasFilter = collect(request()->except(['page']))->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
        $filterUrl = fn($extra = []) => route(
            'superadmin.school.devices.index',
            array_filter(['company_id' => request('company_id')] + $extra, fn($v) => $v !== null && $v !== ''),
        );
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Perangkat Kiosk</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.school.devices.create', array_filter(['company_id' => request('company_id')])) }}"
                        class="btn btn-primary"><i class="fas fa-plus"></i> Daftarkan Kiosk</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring Sekolah</div>
                    <div class="breadcrumb-item">Perangkat Kiosk</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Tablet/HP absen murid di tiap sekolah. Kiosk masuk memakai token perangkat, bukan akun user.
                    Menonaktifkan kiosk atau membuat ulang tokennya akan langsung memutus kiosk tersebut.
                </p>

                <div class="row">
                    @foreach ([['Kiosk Aktif', $summary['active'] . ' / ' . $summary['total'], 'primary', 'fa-tablet-alt', ['status' => 'active']], ['Online Sekarang', $summary['online'], 'success', 'fa-wifi', ['connection' => 'online']], ['Terhubung Hari Ini', $summary['today'], 'info', 'fa-calendar-check', ['connection' => 'today']], ['Tidak Aktif > ' . $Ctrl::IDLE_DAYS . ' Hari', $summary['idle'], $summary['idle'] ? 'warning' : 'success', 'fa-exclamation-triangle', ['connection' => 'idle']]] as [$label, $value, $color, $icon, $filter])
                        <div class="col-6 col-lg-3">
                            <a href="{{ $filterUrl($filter) }}" class="text-decoration-none">
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
                            </a>
                        </div>
                    @endforeach
                </div>

                @if ($schoolsWithoutDevice->isNotEmpty() && !request('company_id'))
                    <div class="alert alert-light border">
                        <i class="fas fa-info-circle text-warning"></i>
                        <b>{{ $schoolsWithoutDevice->count() }} sekolah aktif belum punya kiosk:</b>
                        @foreach ($schoolsWithoutDevice as $s)
                            <a
                                href="{{ route('superadmin.school.devices.create', ['company_id' => $s->id]) }}">{{ $s->name }}</a>
                            @if (!$loop->last)
                                ,
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.school.devices.index') }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="this.form.class_id.value=''; this.form.submit()">
                                        <option value="">Semua Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}{{ $s->is_active ? '' : ' (suspend)' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="class_id" class="form-control">
                                        <option value="">Semua Kelas</option>
                                        <option value="general" @selected(request('class_id') === 'general')>Kiosk umum</option>
                                        @foreach ($classOptions as $c)
                                            <option value="{{ $c->id }}" @selected((string) request('class_id') === (string) $c->id)>
                                                {{ $c->name }} · {{ $c->academic_year }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Aktif &amp; Nonaktif</option>
                                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="connection" class="form-control">
                                        <option value="">Semua Koneksi</option>
                                        @foreach ($connection as $v => $l)
                                            <option value="{{ $v }}" @selected(request('connection') === $v)>
                                                {{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control" placeholder="Nama / ID perangkat">
                                </div>
                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.school.devices.export', request()->query()) }}"
                                        class="btn btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a href="{{ route('superadmin.school.devices.index') }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Kiosk</th>
                                        <th>Sekolah</th>
                                        <th>Kelas</th>
                                        <th>Koneksi</th>
                                        <th class="text-center">Absen Hari Ini</th>
                                        <th class="text-center">Total Absen</th>
                                        <th class="text-right" style="width:170px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $d)
                                        @php $st = $Ctrl::state($d); @endphp
                                        <tr class="{{ $d->is_active ? '' : 'text-muted' }}">
                                            <td>
                                                <a href="{{ route('superadmin.school.devices.show', $d->id) }}"
                                                    class="font-weight-bold">{{ $d->name }}</a>
                                                <div class="text-small text-muted">
                                                    {{ $d->device_identifier ?: 'ID perangkat belum diisi' }}</div>
                                            </td>
                                            <td>{{ $companyNames[$d->company_id] ?? '#' . $d->company_id }}</td>
                                            <td>
                                                @if ($d->classRoom)
                                                    {{ $d->classRoom->name }} <span
                                                        class="text-small text-muted">{{ $d->classRoom->academic_year }}</span>
                                                @else
                                                    <span class="text-muted">Umum (semua kelas)</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $st['badge'] }}">{{ $st['label'] }}</span>
                                                @if ($d->last_seen_at)
                                                    <div class="text-small text-muted"
                                                        title="{{ $d->last_seen_at->format('d M Y H:i') }}">
                                                        {{ $d->last_seen_at->diffForHumans() }}</div>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $d->today_count ?: '-' }}</td>
                                            <td class="text-center">{{ number_format($d->attendances_count) }}</td>
                                            <td class="text-right text-nowrap">
                                                <a href="{{ route('superadmin.school.devices.show', $d->id) }}"
                                                    class="btn btn-sm btn-outline-primary" title="Detail"><i
                                                        class="fas fa-eye"></i></a>
                                                <a href="{{ route('superadmin.school.devices.edit', $d->id) }}"
                                                    class="btn btn-sm btn-outline-info" title="Edit"><i
                                                        class="fas fa-pen"></i></a>
                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                    title="{{ $d->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                                    data-toggle="modal" data-target="#schoolActionModal"
                                                    data-method="PATCH"
                                                    data-action="{{ route('superadmin.school.devices.toggle-active', $d->id) }}"
                                                    data-label="{{ $d->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $d->name }}"
                                                    data-button="{{ $d->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                                    data-note="{{ $d->is_active ? 'Kiosk langsung tidak bisa dipakai absen sampai diaktifkan lagi.' : 'Kiosk bisa dipakai lagi dengan token yang sama.' }}">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    title="Hapus" data-toggle="modal" data-target="#schoolActionModal"
                                                    data-action="{{ route('superadmin.school.devices.destroy', $d->id) }}"
                                                    data-label="Hapus Kiosk {{ $d->name }}"
                                                    data-note="{{ number_format($d->attendances_count) }} data absen dari kiosk ini tetap tersimpan, hanya keterangan kiosknya yang kosong.">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">Belum ada kiosk.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
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
