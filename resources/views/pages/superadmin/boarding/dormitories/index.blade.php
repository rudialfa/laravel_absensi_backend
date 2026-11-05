@extends('layouts.app')

@section('title', 'Asrama & Kamar')

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $tabUrl = fn($key, $extra = []) => route(
            'superadmin.boarding.dormitories.index',
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
        $actBtn = fn(
            $action,
            $label,
            $icon,
            $note,
            $button = 'Hapus',
            $method = 'DELETE',
        ) => '<button type="button" class="btn btn-sm btn-outline-danger" title="' .
            e($label) .
            '" data-toggle="modal" data-target="#schoolActionModal"' .
            ' data-action="' .
            e($action) .
            '" data-label="' .
            e($label) .
            '" data-note="' .
            e($note) .
            '" data-button="' .
            e($button) .
            '" data-method="' .
            $method .
            '">
<i class="fas ' .
            $icon .
            '"></i></button>';
        $issues = array_filter(
            [
                'over' => [$summary['over'], 'kamar melebihi kapasitas', 'rooms'],
                'gender_mismatch' => [$summary['gender_mismatch'], 'santri di asrama beda jenis kelamin', 'occupants'],
                'duplicate' => [$summary['duplicate'], 'penempatan dobel (1 santri di 2 kamar)', 'occupants'],
                'not_boarding' => [$summary['not_boarding'], 'penghuni yang bukan santri asrama aktif', 'occupants'],
            ],
            fn($i) => $i[0] > 0,
        );
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Asrama &amp; Kamar</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Pesantren &amp; Asrama</div>
                    <div class="breadcrumb-item">Asrama &amp; Kamar</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">Asrama, kamar, dan penempatan santri di pesantren &amp; sekolah berasrama.</p>

                <div class="row">
                    @foreach ([['Asrama / Kamar', $summary['dormitories'] . ' / ' . $summary['rooms'], 'primary', 'fa-building', 'dormitories'], ['Terisi', $summary['occupied'] . ' / ' . $summary['capacity'] . ($summary['rate'] !== null ? ' (' . $summary['rate'] . '%)' : ''), 'info', 'fa-bed', 'occupants'], ['Santri Belum Dapat Kamar', $summary['unassigned'], $summary['unassigned'] ? 'warning' : 'success', 'fa-user-clock', 'unassigned'], ['Masalah Data', array_sum(array_column($issues, 0)), $issues ? 'danger' : 'success', 'fa-exclamation-triangle', null]] as [$label, $value, $color, $icon, $link])
                        <div class="col-6 col-lg-3">
                            @if ($link)
                                <a href="{{ $tabUrl($link) }}" class="text-decoration-none">
                            @endif
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>{{ $label }}</h4>
                                    </div>
                                    <div class="card-body" style="font-size:17px">{{ $value }}</div>
                                </div>
                            </div>
                            @if ($link)
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($issues)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Perlu dicek:
                        @foreach ($issues as $key => [$n, $label, $tabKey])
                            <a href="{{ $tabUrl($tabKey, ['status' => $key]) }}" class="alert-link">{{ $n }}
                                {{ $label }}</a>
                            @if (!$loop->last)
                                ·
                            @endif
                        @endforeach
                    </div>
                @endif

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ $tabUrl($key) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.boarding.dormitories.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="this.form.dormitory_id && (this.form.dormitory_id.value=''); this.form.submit()">
                                        <option value="">Semua Sekolah / Pesantren</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="gender" class="form-control">
                                        <option value="">Putra &amp; Putri</option>
                                        @foreach ($genders as $v => $l)
                                            <option value="{{ $v }}" @selected(request('gender') === $v)>
                                                {{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if (in_array($tab, ['rooms', 'occupants'], true))
                                    <div class="col-md-2 mb-3">
                                        <select name="dormitory_id" class="form-control" @disabled($dormOptions->isEmpty())>
                                            <option value="">
                                                {{ $dormOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Asrama' }}
                                            </option>
                                            @foreach ($dormOptions as $d)
                                                <option value="{{ $d->id }}" @selected((string) request('dormitory_id') === (string) $d->id)>
                                                    {{ $d->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($tab !== 'unassigned')
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua</option>
                                            @if ($tab === 'dormitories')
                                                <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                                                <option value="no_pengasuh" @selected(request('status') === 'no_pengasuh')>Belum ada pengasuh
                                                </option>
                                            @elseif ($tab === 'rooms')
                                                <option value="full" @selected(request('status') === 'full')>Penuh</option>
                                                <option value="over" @selected(request('status') === 'over')>Melebihi kapasitas
                                                </option>
                                                <option value="empty" @selected(request('status') === 'empty')>Kosong</option>
                                                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                                            @else
                                                <option value="gender_mismatch" @selected(request('status') === 'gender_mismatch')>Beda jenis
                                                    kelamin</option>
                                                <option value="duplicate" @selected(request('status') === 'duplicate')>Penempatan dobel
                                                </option>
                                                <option value="not_boarding" @selected(request('status') === 'not_boarding')>Bukan santri
                                                    asrama aktif</option>
                                            @endif
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control"
                                        placeholder="{{ in_array($tab, ['occupants', 'unassigned'], true) ? 'Nama / NIS santri' : 'Nama ' . ($tab === 'rooms' ? 'kamar' : 'asrama') }}">
                                </div>
                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.boarding.dormitories.export', request()->query() + ['tab' => $tab]) }}"
                                        class="btn btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.boarding.dormitories.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                {{-- ---------- ASRAMA ---------- --}}
                                @if ($tab === 'dormitories')
                                    <thead>
                                        <tr>
                                            <th>Asrama</th>
                                            <th>Sekolah</th>
                                            <th>Pengasuh</th>
                                            <th class="text-center">Kamar</th>
                                            <th style="width:180px">Keterisian</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $d)
                                            @php
                                                $cap = (int) $d->capacity_total;
                                                $pct = $cap ? min(100, round(($d->occupants / $cap) * 100)) : 0;
                                            @endphp
                                            <tr class="{{ $d->is_active ? '' : 'text-muted' }}">
                                                <td>
                                                    <a href="{{ route('superadmin.boarding.dormitories.show', $d->id) }}"
                                                        class="font-weight-bold">{{ $d->name }}</a>
                                                    <span
                                                        class="badge badge-{{ $d->gender === 'L' ? 'info' : 'danger' }}">{{ $genders[$d->gender] ?? $d->gender }}</span>
                                                    @unless ($d->is_active)
                                                        <span class="badge badge-secondary">nonaktif</span>
                                                    @endunless
                                                </td>
                                                <td class="text-small">{{ $companyNames[$d->company_id] ?? '-' }}</td>
                                                <td>
                                                    @if ($d->pengasuh)
                                                        {{ $d->pengasuh->name }}
                                                        @if ((int) $d->pengasuh->company_id !== (int) $d->company_id)
                                                            <span class="badge badge-danger">beda sekolah</span>
                                                        @endif
                                                        <div class="text-small text-muted">{{ $d->pengasuh->phone }}</div>
                                                    @else
                                                        <span class="text-warning text-small">Belum ada</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $d->active_rooms_count }}@if ($d->rooms_count > $d->active_rooms_count)
                                                        <div class="text-small text-muted">
                                                            +{{ $d->rooms_count - $d->active_rooms_count }} nonaktif</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-fill mr-2" style="height:8px">
                                                            <div class="progress-bar bg-{{ $d->occupants > $cap ? 'danger' : ($pct >= 90 ? 'warning' : 'primary') }}"
                                                                style="width:{{ $pct }}%"></div>
                                                        </div>
                                                        <span
                                                            class="text-small">{{ $d->occupants }}/{{ $cap }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.boarding.dormitories.show', $d->id) }}"
                                                        class="btn btn-sm btn-outline-primary"><i
                                                            class="fas fa-eye"></i></a>
                                                    @if (!$d->occupants)
                                                        {!! $actBtn(
                                                            route('superadmin.boarding.dormitories.destroy', ['dormitories', $d->id]),
                                                            'Hapus asrama ' . $d->name,
                                                            'fa-trash',
                                                            'Semua kamar & riwayat penempatannya ikut terhapus.',
                                                        ) !!}
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Belum ada asrama.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- KAMAR ---------- --}}
                                @elseif ($tab === 'rooms')
                                    <thead>
                                        <tr>
                                            <th>Kamar</th>
                                            <th>Asrama</th>
                                            <th style="width:180px">Penghuni</th>
                                            <th>Catatan</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $r)
                                            @php $pct = $r->capacity ? min(100, round($r->active_assignments_count / $r->capacity * 100)) : 0; @endphp
                                            <tr class="{{ $r->is_active ? '' : 'text-muted' }}">
                                                <td class="font-weight-bold">
                                                    {{ $r->name }}@unless ($r->is_active)
                                                    <span class="badge badge-secondary">nonaktif</span>
                                                @endunless
                                            </td>
                                            <td>
                                                @if ($r->dormitory)
                                                    <a
                                                        href="{{ route('superadmin.boarding.dormitories.show', $r->dormitory->id) }}">{{ $r->dormitory->name }}</a>
                                                @endif
                                                <div class="text-small text-muted">
                                                    {{ $companyNames[$r->dormitory->company_id ?? 0] ?? '' }}</div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-fill mr-2" style="height:8px">
                                                        <div class="progress-bar bg-{{ $r->active_assignments_count > $r->capacity ? 'danger' : ($pct >= 100 ? 'warning' : 'primary') }}"
                                                            style="width:{{ $pct }}%"></div>
                                                    </div>
                                                    <span
                                                        class="text-small">{{ $r->active_assignments_count }}/{{ $r->capacity }}</span>
                                                </div>
                                            </td>
                                            <td class="text-small">
                                                @if ($r->active_assignments_count > $r->capacity)
                                                    <span class="badge badge-danger">melebihi kapasitas</span>
                                                @endif
                                                @if ($r->gender_mismatch)
                                                    <span class="badge badge-danger">{{ $r->gender_mismatch }} santri
                                                        beda jenis kelamin</span>
                                                @endif
                                                @if (!$r->is_active && $r->active_assignments_count)
                                                    <span class="badge badge-warning">nonaktif tapi dihuni</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if (!$r->active_assignments_count)
                                                    {!! $actBtn(
                                                        route('superadmin.boarding.dormitories.destroy', ['rooms', $r->id]),
                                                        'Hapus kamar ' . $r->name,
                                                        'fa-trash',
                                                        'Riwayat penempatan di kamar ini ikut terhapus.',
                                                    ) !!}
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Tidak ada kamar.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                {{-- ---------- PENGHUNI ---------- --}}
                            @elseif ($tab === 'occupants')
                                <thead>
                                    <tr>
                                        <th>Santri</th>
                                        <th>Asrama / Kamar</th>
                                        <th>Masuk</th>
                                        <th>Catatan</th>
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $a)
                                        @php
                                            $st = $a->student;
                                            $dorm = $a->room->dormitory ?? null;
                                        @endphp
                                        <tr>
                                            <td>
                                                @if ($st)
                                                    @if ($studentUrl($st->id))
                                                        <a href="{{ $studentUrl($st->id) }}">{{ $st->name }}</a>
                                                    @else
                                                        {{ $st->name }}
                                                    @endif
                                                    <div class="text-small text-muted">{{ $st->nis }} ·
                                                        {{ $st->gender }} · {{ $st->classRoom->name ?? '-' }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($dorm)
                                                    <a
                                                        href="{{ route('superadmin.boarding.dormitories.show', $dorm->id) }}">{{ $dorm->name }}</a>
                                                @endif · {{ $a->room->name ?? '-' }}
                                                <div class="text-small text-muted">
                                                    {{ $companyNames[$dorm->company_id ?? 0] ?? '' }}</div>
                                            </td>
                                            <td class="text-small">{{ $fmtDate($a->moved_in_at) }}<div
                                                    class="text-muted">{{ $a->assignedBy->name ?? '' }}</div>
                                            </td>
                                            <td class="text-small">
                                                @if ($st && $dorm && $st->gender !== $dorm->gender)
                                                    <span class="badge badge-danger">beda jenis kelamin</span>
                                                @endif
                                                @if ($a->active_count > 1)
                                                    <span class="badge badge-danger">dobel ({{ $a->active_count }}
                                                        kamar)</span>
                                                @endif
                                                @if ($st && ($st->trashed() || !$st->is_active))
                                                    <span class="badge badge-warning">santri
                                                        {{ $st->trashed() ? 'terhapus' : 'nonaktif' }}</span>
                                                @elseif ($st && !$st->is_boarding)
                                                    <span class="badge badge-warning">bukan santri asrama</span>
                                                @endif
                                                @if ($a->notes)
                                                    <div>{{ \Illuminate\Support\Str::limit($a->notes, 50) }}</div>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                {!! $actBtn(
                                                    route('superadmin.boarding.dormitories.checkout', $a->id),
                                                    'Keluarkan ' . ($st->name ?? 'santri') . ' dari kamar',
                                                    'fa-sign-out-alt',
                                                    'Mengisi tanggal keluar hari ini. Riwayat penempatan tetap tersimpan.',
                                                    'Keluarkan',
                                                    'PATCH',
                                                ) !!}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Tidak ada penghuni.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                {{-- ---------- BELUM DAPAT KAMAR ---------- --}}
                            @else
                                <thead>
                                    <tr>
                                        <th>Santri</th>
                                        <th>Sekolah</th>
                                        <th>L/P</th>
                                        <th>Kelas</th>
                                        <th>Terdaftar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $s)
                                        <tr>
                                            <td>
                                                @if ($studentUrl($s->id))
                                                    <a href="{{ $studentUrl($s->id) }}">{{ $s->name }}</a>
                                                @else
                                                    {{ $s->name }}
                                                @endif
                                                <div class="text-small text-muted">NIS {{ $s->nis }}</div>
                                            </td>
                                            <td class="text-small">{{ $companyNames[$s->company_id] ?? '-' }}</td>
                                            <td>{{ $s->gender }}</td>
                                            <td>{{ $s->classRoom->name ?? '-' }}</td>
                                            <td class="text-small">{{ $fmtDate($s->enrolled_at ?? $s->created_at) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Semua santri asrama
                                                sudah punya kamar.</td>
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
