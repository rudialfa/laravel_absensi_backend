@extends('layouts.app')

@section('title', 'Asrama ' . $dorm->name)

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $studentUrl = fn($id) => Route::has('superadmin.school.students.show')
            ? route('superadmin.school.students.show', $id)
            : null;
        $activeRooms = $rooms->where('is_active', true);
        $capacity = (int) $activeRooms->sum('capacity');
        $occupied = $rooms->sum(fn($r) => $r->activeAssignments->count());
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.boarding.dormitories.index', ['company_id' => $dorm->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $dorm->name }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.boarding.dormitories.index') }}">Asrama</a>
                    </div>
                    <div class="breadcrumb-item">{{ $dorm->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    @foreach ([['Kamar Aktif', $activeRooms->count(), 'primary', 'fa-door-closed'], ['Kapasitas', $capacity, 'info', 'fa-bed'], ['Penghuni', $occupied, $occupied > $capacity ? 'danger' : 'success', 'fa-users'], ['Sisa Tempat', max(0, $capacity - $occupied), 'secondary', 'fa-plus-square']] as [$label, $value, $color, $icon])
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
                        <div class="row">
                            @forelse ($rooms as $r)
                                @php $count = $r->activeAssignments->count(); @endphp
                                <div class="col-12 col-md-6">
                                    <div class="card {{ $count > $r->capacity ? 'border border-danger' : '' }}">
                                        <div class="card-header">
                                            <h4>{{ $r->name }} @unless ($r->is_active)
                                                    <span class="badge badge-secondary">nonaktif</span>
                                                @endunless
                                            </h4>
                                            <div class="card-header-action">
                                                <span
                                                    class="badge badge-{{ $count > $r->capacity ? 'danger' : ($count >= $r->capacity ? 'warning' : 'light') }}">{{ $count }}/{{ $r->capacity }}</span>
                                            </div>
                                        </div>
                                        <div class="card-body py-2">
                                            @forelse ($r->activeAssignments as $a)
                                                @php $st = $a->student; @endphp
                                                <div
                                                    class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                                    <div>
                                                        @if ($st)
                                                            @if ($studentUrl($st->id))
                                                                <a
                                                                    href="{{ $studentUrl($st->id) }}">{{ $st->name }}</a>
                                                            @else
                                                                {{ $st->name }}
                                                            @endif
                                                            <span
                                                                class="text-small text-muted">{{ $st->classRoom->name ?? '' }}</span>
                                                            @if ($st->gender !== $dorm->gender)
                                                                <span class="badge badge-danger">beda jenis kelamin</span>
                                                            @endif
                                                            @if ($st->trashed() || !$st->is_active)
                                                                <span class="badge badge-warning">nonaktif</span>
                                                            @endif
                                                        @endif
                                                        <div class="text-small text-muted">sejak
                                                            {{ $fmtDate($a->moved_in_at) }}</div>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-light"
                                                        title="Keluarkan dari kamar" data-toggle="modal"
                                                        data-target="#schoolActionModal" data-method="PATCH"
                                                        data-action="{{ route('superadmin.boarding.dormitories.checkout', $a->id) }}"
                                                        data-label="Keluarkan {{ $st->name ?? 'santri' }} dari kamar {{ $r->name }}"
                                                        data-button="Keluarkan"
                                                        data-note="Mengisi tanggal keluar hari ini. Riwayat penempatan tetap tersimpan."><i
                                                            class="fas fa-sign-out-alt"></i></button>
                                                </div>
                                            @empty
                                                <p class="text-muted text-small mb-2">Kosong.</p>
                                                <button type="button" class="btn btn-sm btn-outline-danger mb-2"
                                                    data-toggle="modal" data-target="#schoolActionModal"
                                                    data-action="{{ route('superadmin.boarding.dormitories.destroy', ['rooms', $r->id]) }}"
                                                    data-label="Hapus kamar {{ $r->name }}"
                                                    data-note="Riwayat penempatan di kamar ini ikut terhapus."><i
                                                        class="fas fa-trash"></i> Hapus kamar</button>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-body text-center text-muted">Belum ada kamar.</div>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Info Asrama</h4>
                                <div class="card-header-action"><span
                                        class="badge badge-{{ $dorm->gender === 'L' ? 'info' : 'danger' }}">{{ $genders[$dorm->gender] ?? $dorm->gender }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Sekolah</th>
                                        <td class="text-right">
                                            @if ($dorm->company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $dorm->company->id) }}">{{ $dorm->company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Pengasuh</th>
                                        <td class="text-right">
                                            {{ $dorm->pengasuh->name ?? 'Belum ada' }}
                                            @if ($dorm->pengasuh)
                                                <div class="text-small text-muted">
                                                    {{ $dorm->pengasuh->phone ?: $dorm->pengasuh->email }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td class="text-right">{{ $dorm->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Alamat</th>
                                        <td class="text-right">{{ $dorm->address ?: '-' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Keluar / Pindah</h4>
                            </div>
                            <div class="card-body">
                                @forelse ($history as $h)
                                    <div class="d-flex justify-content-between mb-1 text-small">
                                        <span>{{ $h->student->name ?? '-' }} <span class="text-muted">·
                                                {{ $h->room->name ?? '' }}</span></span>
                                        <span class="text-muted">{{ $fmtDate($h->moved_out_at) }}</span>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Belum ada.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Asrama</h6>
                                @if ($occupied)
                                    <p class="small text-muted mb-0">Asrama masih dihuni {{ $occupied }} santri.
                                        Keluarkan atau pindahkan dulu.</p>
                                @else
                                    <p class="small text-muted">Semua kamar & riwayat penempatannya ikut terhapus.</p>
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                        data-target="#schoolActionModal"
                                        data-action="{{ route('superadmin.boarding.dormitories.destroy', ['dormitories', $dorm->id]) }}"
                                        data-label="Hapus asrama {{ $dorm->name }}"><i class="fas fa-trash"></i>
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
