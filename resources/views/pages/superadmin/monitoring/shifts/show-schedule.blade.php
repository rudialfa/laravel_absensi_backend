@extends('layouts.app')

@section('title', 'Jadwal ' . $schedule->title)

@section('main')
    @php
        $s = $schedule;
        $statusBadge = ['upcoming' => 'info', 'done' => 'success', 'canceled' => 'secondary'];
        $pLabel = ['invited' => 'Diundang', 'accepted' => 'Hadir', 'declined' => 'Menolak'];
        $pBadge = ['invited' => 'warning', 'accepted' => 'success', 'declined' => 'danger'];
        $location = is_array($s->location) ? $s->location : [];
        $offsets = is_array($s->reminder_offsets) ? $s->reminder_offsets : [];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.monitoring.shifts.index', ['tab' => 'schedules', 'company_id' => $s->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Jadwal</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.monitoring.shifts.index', ['tab' => 'schedules']) }}">Jadwal</a>
                    </div>
                    <div class="breadcrumb-item">#{{ $s->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $s->title }}</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $statusBadge[$s->status] ?? 'light' }}">{{ $scheduleStatus[$s->status] ?? $s->status }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-3">
                                    <tr>
                                        <th style="width:140px">Jenis</th>
                                        <td>{{ $scheduleTypes[$s->type] ?? $s->type }}</td>
                                    </tr>
                                    <tr>
                                        <th>Waktu</th>
                                        <td>
                                            {{ optional($s->start_datetime)->translatedFormat('l, d M Y H:i') }}
                                            @if ($s->end_datetime)
                                                –
                                                {{ $s->end_datetime->format($s->end_datetime->isSameDay($s->start_datetime) ? 'H:i' : 'd M Y H:i') }}
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($s->is_recurring)
                                        <tr>
                                            <th>Berulang</th>
                                            <td>{{ ['daily' => 'Setiap hari', 'weekly' => 'Setiap minggu', 'monthly' => 'Setiap bulan'][$s->recurrence_type] ?? $s->recurrence_type }}
                                                sampai
                                                {{ $s->recurrence_end_date ? \Carbon\Carbon::parse($s->recurrence_end_date)->format('d M Y') : 'seterusnya' }}
                                            </td>
                                        </tr>
                                    @endif
                                    @if (!empty($location))
                                        <tr>
                                            <th>Lokasi</th>
                                            <td>
                                                {{ $location['name'] ?? ($location['address'] ?? '') }}
                                                @if (!empty($location['lat']) && !empty($location['lng']))
                                                    <a href="https://www.google.com/maps?q={{ $location['lat'] }},{{ $location['lng'] }}"
                                                        target="_blank" rel="noopener" class="ml-1"><i
                                                            class="fas fa-map-marker-alt"></i> peta</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                    @if (!empty($offsets))
                                        <tr>
                                            <th>Pengingat</th>
                                            <td>{{ collect($offsets)->map(fn($m) => $m >= 60 ? $m / 60 . ' jam' : $m . ' menit')->implode(', ') }}
                                                sebelumnya</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th>Perusahaan</th>
                                        <td>
                                            @if ($company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $company->id) }}">{{ $company->name }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Pemilik</th>
                                        <td>{{ $s->user->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat oleh</th>
                                        <td>{{ $s->creator->name ?? '-' }} ·
                                            {{ optional($s->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                </table>
                                <h6 class="small text-muted">Deskripsi</h6>
                                <div class="bg-light rounded p-3">{!! nl2br(e($s->description ?: '-')) !!}</div>
                            </div>
                            <div class="card-footer bg-whitesmoke text-right">
                                <button type="button" class="btn btn-outline-danger" data-toggle="modal"
                                    data-target="#shiftDeleteModal"
                                    data-action="{{ route('superadmin.monitoring.shifts.destroy', ['schedules', $s->id]) }}"
                                    data-label="jadwal {{ $s->title }}">
                                    <i class="fas fa-trash"></i> Hapus Jadwal
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>Peserta ({{ $s->participants->count() }})</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @forelse ($s->participants as $p)
                                        <tr>
                                            <td class="pl-4">
                                                {{ $p->user->name ?? '-' }}
                                                <div class="text-small text-muted">{{ $p->user->department ?? '' }}</div>
                                                @if ($p->note)
                                                    <div class="text-small">"{{ $p->note }}"</div>
                                                @endif
                                            </td>
                                            <td class="pr-4 text-right">
                                                <span
                                                    class="badge badge-{{ $pBadge[$p->status] ?? 'light' }}">{{ $pLabel[$p->status] ?? $p->status }}</span>
                                                @if ($p->responded_at)
                                                    <div class="text-small text-muted">
                                                        {{ \Carbon\Carbon::parse($p->responded_at)->format('d M H:i') }}
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted">Tanpa peserta</td>
                                        </tr>
                                    @endforelse
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
