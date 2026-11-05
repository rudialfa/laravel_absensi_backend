@extends('layouts.app')

@section('title', 'Detail Absen')

@section('main')
    @php
        $a = $attendance;
        $company = $a->company;
        $mapLink = fn($latlon) => $latlon
            ? 'https://www.google.com/maps?q=' . urlencode(str_replace(' ', '', $latlon))
            : null;
        $permTypes = [
            'tidak_masuk' => 'Tidak masuk',
            'datang_terlambat' => 'Datang terlambat',
            'pulang_cepat' => 'Pulang cepat',
            'keluar_kantor' => 'Keluar kantor',
            'dinas_luar' => 'Dinas luar',
        ];
        $radiusM = $company ? round((float) $company->radius_km * 1000) : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('superadmin.monitoring.attendances.index') }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Absen</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.monitoring.attendances.index') }}">Absensi</a></div>
                    <div class="breadcrumb-item">#{{ $a->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $a->user->name ?? '(user terhapus)' }} ·
                                    {{ optional($a->date)->translatedFormat('l, d F Y') }}</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $badge[$a->status] ?? 'secondary' }}">{{ $statuses[$a->status] ?? ucfirst((string) $a->status) }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @foreach ([['Masuk', $a->time_in, $a->scheduled_in, $a->photo_in, $a->latlon_in, $a->distance_in_m, $a->is_outside_radius_in], ['Pulang', $a->time_out, $a->scheduled_out, $a->photo_out, $a->latlon_out, $a->distance_out_m, $a->is_outside_radius_out]] as [$label, $time, $sched, $photo, $latlon, $dist, $outside])
                                        <div class="col-md-6 mb-3">
                                            <div class="border rounded p-3 h-100">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="mb-1">{{ $label }}</h6>
                                                    @if ($sched)
                                                        <small class="text-muted">jadwal {{ substr($sched, 0, 5) }}</small>
                                                    @endif
                                                </div>
                                                <div class="h3 mb-2">{{ $time ? substr($time, 0, 8) : '—' }}</div>
                                                @if ($photo)
                                                    <a href="{{ asset($photo) }}" target="_blank">
                                                        <img src="{{ asset($photo) }}" alt="Foto {{ strtolower($label) }}"
                                                            class="img-fluid rounded border mb-2"
                                                            style="max-height:260px;width:100%;object-fit:cover">
                                                    </a>
                                                @elseif ($time)
                                                    <div class="text-muted small mb-2"><i class="fas fa-camera"></i> Tanpa
                                                        foto</div>
                                                @endif
                                                @if ($latlon)
                                                    <div class="small">
                                                        <i
                                                            class="fas fa-map-marker-alt {{ $outside ? 'text-danger' : 'text-success' }}"></i>
                                                        <a href="{{ $mapLink($latlon) }}" target="_blank"
                                                            rel="noopener">{{ $latlon }}</a>
                                                    </div>
                                                @endif
                                                @if ($dist !== null)
                                                    <div class="small {{ $outside ? 'text-danger' : 'text-muted' }}">
                                                        {{ number_format($dist) }} m dari
                                                        kantor{{ $radiusM ? ' (radius ' . number_format($radiusM) . ' m)' : '' }}
                                                        {{ $outside ? '· di luar radius' : '' }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="h5 mb-0 {{ $a->late_minutes ? 'text-warning' : '' }}">
                                            {{ (int) $a->late_minutes }}</div>
                                        <small class="text-muted">menit telat</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 mb-0">{{ (int) $a->early_leave_minutes }}</div>
                                        <small class="text-muted">menit pulang cepat</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 mb-0">{{ (int) $a->overtime_minutes }}</div>
                                        <small class="text-muted">menit
                                            lembur{{ $a->approved_overtime ? ' (disetujui)' : '' }}</small>
                                    </div>
                                </div>

                                @if ($a->note)
                                    <div class="alert alert-light border mt-3 mb-0"><i class="fas fa-sticky-note"></i>
                                        {{ $a->note }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Informasi</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:110px">Karyawan</th>
                                        <td>
                                            @if ($a->user && \Illuminate\Support\Facades\Route::has('superadmin.users.show'))
                                                <a
                                                    href="{{ route('superadmin.users.show', $a->user_id) }}">{{ $a->user->name }}</a>
                                            @else
                                                {{ $a->user->name ?? '-' }}
                                            @endif
                                            <div class="text-small text-muted">
                                                {{ collect([$a->user->position ?? null, $a->user->department ?? null])->filter()->implode(' · ') }}
                                            </div>
                                        </td>
                                    </tr>
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
                                        <th>Shift</th>
                                        <td>
                                            @if ($a->shift)
                                                {{ $a->shift->name }}
                                                ({{ substr($a->shift->start_time, 0, 5) }}–{{ substr($a->shift->end_time, 0, 5) }})
                                            @else
                                                Jam default perusahaan
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Cara absen</th>
                                        <td>
                                            @if ($a->markedBy)
                                                <span class="text-info"><i class="fas fa-user-edit"></i> Diinput HR:
                                                    {{ $a->markedBy->name }}</span>
                                            @else
                                                Mandiri lewat aplikasi
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($a->permission)
                                        <tr>
                                            <th>Izin terkait</th>
                                            <td>
                                                {{ $permTypes[$a->permission->type] ?? $a->permission->type }}
                                                <div class="text-small text-muted">
                                                    {{ \Illuminate\Support\Str::limit($a->permission->reason, 80) }}</div>
                                            </td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th>Tercatat</th>
                                        <td class="text-small">{{ optional($a->created_at)->format('d M Y H:i') }}
                                            @if ($a->updated_at && $a->updated_at->ne($a->created_at))
                                                <br>diubah {{ $a->updated_at->format('d M Y H:i') }}
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>14 Absen Sebelumnya</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @forelse ($recent as $r)
                                        <tr>
                                            <td class="pl-3 text-small"><a
                                                    href="{{ route('superadmin.monitoring.attendances.show', $r->id) }}">{{ optional($r->date)->format('d M') }}</a>
                                            </td>
                                            <td class="text-small">{{ $r->time_in ? substr($r->time_in, 0, 5) : '-' }} –
                                                {{ $r->time_out ? substr($r->time_out, 0, 5) : '-' }}</td>
                                            <td class="pr-3 text-right"><span
                                                    class="badge badge-{{ $badge[$r->status] ?? 'secondary' }}">{{ $statuses[$r->status] ?? $r->status }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted">Belum ada</td>
                                        </tr>
                                    @endforelse
                                </table>
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Data Absen</h6>
                                <p class="small text-muted">Untuk data ganda atau salah input. Foto ikut terhapus. Koreksi
                                    jam sebaiknya dilakukan HR dari aplikasi.</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#deleteModal">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.monitoring.attendances.destroy', $a->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Hapus Data Absen</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small">Absen <b>{{ $a->user->name ?? '-' }}</b> tanggal
                            <b>{{ optional($a->date)->format('d M Y') }}</b> akan dihapus permanen.
                            Karyawan bisa absen ulang di hari yang sama kalau tanggalnya hari ini.
                        </p>
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="500" required
                                placeholder="Contoh: Data ganda karena aplikasi error"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            $('#deleteModal').appendTo('body');
        });
    </script>
@endpush
