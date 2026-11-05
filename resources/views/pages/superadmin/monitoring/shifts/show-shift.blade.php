@extends('layouts.app')

@section('title', 'Shift ' . $shift->name)

@section('main')
    @php
        $hm = fn($t) => $t ? substr($t, 0, 5) : '-';
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $attStatus = \App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminAttendanceController::STATUSES;
        $used = $usage['attendances'] + $usage['assignments'] + $usage['overrides'];
        $start = \Carbon\Carbon::parse($shift->start_time);
        $end = \Carbon\Carbon::parse($shift->end_time);
        $overnight = $end->lte($start);
        if ($overnight) {
            $end->addDay();
        }
        $durationMin = $start->diffInMinutes($end);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.monitoring.shifts.index', ['tab' => 'shifts', 'company_id' => $shift->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Shift</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.monitoring.shifts.index') }}">Shift &amp;
                            Jadwal</a></div>
                    <div class="breadcrumb-item">{{ $shift->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-body text-center pt-4">
                                <h4 class="mb-1">{{ $shift->name }}</h4>
                                @if ($shift->is_default)
                                    <span class="badge badge-primary mb-2">Shift default</span>
                                @endif
                                <div class="h2 text-primary mb-0">{{ $hm($shift->start_time) }} –
                                    {{ $hm($shift->end_time) }}</div>
                                <div class="text-muted">{{ intdiv($durationMin, 60) }} jam
                                    {{ $durationMin % 60 ? $durationMin % 60 . ' menit' : '' }}
                                    {{ $overnight ? '· lewat tengah malam' : '' }}</div>
                            </div>
                            <div class="card-body border-top">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Perusahaan</th>
                                        <td class="text-right">
                                            @if ($company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $company->id) }}">{{ $company->name }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Toleransi telat</th>
                                        <td class="text-right">{{ (int) $shift->grace_period_minutes }} menit</td>
                                    </tr>
                                    <tr>
                                        <th>Dipakai di absen</th>
                                        <td class="text-right">{{ $usage['attendances'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>Penugasan grup</th>
                                        <td class="text-right">{{ $usage['assignments'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>Shift khusus</th>
                                        <td class="text-right">{{ $usage['overrides'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($shift->created_at)->format('d M Y') }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer bg-whitesmoke">
                                @if ($used === 0)
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                        data-target="#shiftDeleteModal"
                                        data-action="{{ route('superadmin.monitoring.shifts.destroy', ['shifts', $shift->id]) }}"
                                        data-label="shift {{ $shift->name }}">
                                        <i class="fas fa-trash"></i> Hapus Shift
                                    </button>
                                @else
                                    <small class="text-muted d-block text-center">Shift masih dipakai, tidak bisa
                                        dihapus.</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Grup yang Memakai Shift Ini</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @forelse ($assignments as $as)
                                        <tr>
                                            <td class="pl-4">
                                                @if ($as->group)
                                                    <a
                                                        href="{{ route('superadmin.monitoring.shifts.show', ['groups', $as->shift_group_id]) }}">{{ $as->group->name }}</a>
                                                @else
                                                    (grup terhapus)
                                                @endif
                                            </td>
                                            <td class="pr-4 text-right">{{ $fmtDate($as->start_date) }} –
                                                {{ $as->end_date ? $fmtDate($as->end_date) : 'seterusnya' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted">Tidak ada</td>
                                        </tr>
                                    @endforelse
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Shift Khusus Karyawan</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @forelse ($overrides as $ov)
                                        <tr>
                                            <td class="pl-4">{{ $ov->user->name ?? '-' }}</td>
                                            <td>{{ $fmtDate($ov->start_date) }} –
                                                {{ $ov->end_date ? $fmtDate($ov->end_date) : 'seterusnya' }}</td>
                                            <td class="pr-4 text-right"><span
                                                    class="badge badge-{{ $ov->status === 'active' ? 'success' : 'secondary' }}">{{ $ov->status === 'active' ? 'Aktif' : 'Dibatalkan' }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted">Tidak ada</td>
                                        </tr>
                                    @endforelse
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>10 Absen Terakhir dengan Shift Ini</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @forelse ($recentAtt as $att)
                                        <tr>
                                            <td class="pl-4"><a
                                                    href="{{ route('superadmin.monitoring.attendances.show', $att->id) }}">{{ optional($att->date)->format('d M Y') }}</a>
                                            </td>
                                            <td>{{ $att->user->name ?? '-' }}</td>
                                            <td>{{ $hm($att->time_in) }} – {{ $hm($att->time_out) }}</td>
                                            <td class="pr-4 text-right">{{ $attStatus[$att->status] ?? $att->status }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted">Belum ada</td>
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
