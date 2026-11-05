@extends('layouts.app')

@section('title', 'Kiosk ' . $device->name)

@section('main')
    @php
        $statusBadge = [
            'hadir' => 'success',
            'terlambat' => 'warning',
            'izin' => 'info',
            'sakit' => 'primary',
            'alpa' => 'danger',
        ];
        $stVal = fn($s) => $s instanceof \BackedEnum ? $s->value : $s;
        $maxDay = max(1, $days->max('total'));
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.devices.index', ['company_id' => $device->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $device->name }}</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.school.devices.edit', $device->id) }}" class="btn btn-primary"><i
                            class="fas fa-pen"></i> Edit</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.school.devices.index') }}">Perangkat
                            Kiosk</a></div>
                    <div class="breadcrumb-item">{{ $device->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                {{-- Token hanya tampil sekali (setelah dibuat / dibuat ulang) --}}
                @if ($newToken)
                    <div class="card border border-success">
                        <div class="card-header">
                            <h4 class="text-success"><i class="fas fa-key"></i> Token Kiosk</h4>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">Masukkan token ini di aplikasi kiosk. <b>Token hanya ditampilkan sekali
                                    ini.</b> Kalau hilang, buat token baru.</p>
                            <div class="input-group">
                                <input type="text" id="deviceToken" class="form-control text-monospace"
                                    value="{{ $newToken }}" readonly onclick="this.select()">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-success" id="copyToken"><i
                                            class="fas fa-copy"></i> Salin</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($device->company && !$device->company->is_active)
                    <div class="alert alert-warning"><i class="fas fa-ban"></i> Sekolah ini sedang <b>disuspend</b>. Kiosk
                        ditolak walaupun statusnya aktif.</div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Info Kiosk</h4>
                                <div class="card-header-action"><span
                                        class="badge badge-{{ $state['badge'] }}">{{ $state['label'] }}</span></div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Sekolah</th>
                                        <td class="text-right">
                                            @if ($device->company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $device->company->id) }}">{{ $device->company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Kelas</th>
                                        <td class="text-right">
                                            @if ($device->classRoom)
                                                @if (Route::has('superadmin.school.classes.show'))
                                                    <a
                                                        href="{{ route('superadmin.school.classes.show', $device->classRoom->id) }}">{{ $device->classRoom->name }}</a>
                                                @else
                                                    {{ $device->classRoom->name }}
                                                @endif
                                                <div class="text-small text-muted">
                                                    {{ $device->classRoom->academic_year }}{{ $device->classRoom->is_active ? '' : ' · arsip' }}
                                                </div>
                                            @else
                                                <span class="text-muted">Umum (semua kelas)</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>ID perangkat</th>
                                        <td class="text-right">{{ $device->device_identifier ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Terakhir aktif</th>
                                        <td class="text-right">
                                            @if ($device->last_seen_at)
                                                {{ $device->last_seen_at->format('d M Y H:i') }}
                                                <div class="text-small text-muted">
                                                    {{ $device->last_seen_at->diffForHumans() }}</div>
                                            @else
                                                <span class="text-muted">Belum pernah</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Total absen</th>
                                        <td class="text-right">{{ number_format($device->attendances_count) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Pertama dipakai</th>
                                        <td class="text-right">{{ $fmtDate($firstUsed) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Terakhir dipakai</th>
                                        <td class="text-right">{{ $fmtDate($lastUsed) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Didaftarkan</th>
                                        <td class="text-right">{{ $device->registeredBy->name ?? '-' }}<div
                                                class="text-small text-muted">
                                                {{ optional($device->created_at)->format('d M Y H:i') }}</div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Aksi</h4>
                            </div>
                            <div class="card-body">
                                <button type="button" class="btn btn-outline-warning btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal" data-method="PATCH"
                                    data-action="{{ route('superadmin.school.devices.toggle-active', $device->id) }}"
                                    data-label="{{ $device->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $device->name }}"
                                    data-button="{{ $device->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                    data-note="{{ $device->is_active ? 'Kiosk langsung tidak bisa dipakai absen sampai diaktifkan lagi.' : 'Kiosk bisa dipakai lagi dengan token yang sama.' }}">
                                    <i class="fas fa-power-off"></i> {{ $device->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>

                                <button type="button" class="btn btn-outline-info btn-block" data-toggle="modal"
                                    data-target="#tokenModal">
                                    <i class="fas fa-key"></i> Buat Token Baru
                                </button>

                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal"
                                    data-action="{{ route('superadmin.school.devices.destroy', $device->id) }}"
                                    data-label="Hapus Kiosk {{ $device->name }}"
                                    data-note="{{ number_format($device->attendances_count) }} data absen dari kiosk ini tetap tersimpan, hanya keterangan kiosknya yang kosong.">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Pemakaian 14 Hari Terakhir</h4>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-end" style="height:140px">
                                    @foreach ($days as $day)
                                        <div class="flex-fill text-center px-1"
                                            title="{{ $day['date']->translatedFormat('l, d M') }}: {{ $day['total'] }} absen">
                                            <div class="text-small text-muted">{{ $day['total'] ?: '' }}</div>
                                            <div class="rounded-top {{ $day['date']->isWeekend() ? 'bg-secondary' : 'bg-primary' }}"
                                                style="height:{{ $day['total'] ? max(4, round(($day['total'] / $maxDay) * 100)) : 2 }}px; opacity:{{ $day['total'] ? 1 : 0.3 }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="d-flex border-top pt-1">
                                    @foreach ($days as $day)
                                        <div class="flex-fill text-center text-small {{ $day['date']->isToday() ? 'font-weight-bold' : 'text-muted' }}"
                                            style="font-size:10px">{{ $day['date']->format('d/m') }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Absen Terakhir dari Kiosk Ini</h4>
                                @if (Route::has('superadmin.school.attendances.index'))
                                    <div class="card-header-action">
                                        <a href="{{ route('superadmin.school.attendances.index', ['company_id' => $device->company_id, 'device_id' => $device->id]) }}"
                                            class="btn btn-sm btn-light">Semua</a>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Jam</th>
                                                <th>Murid</th>
                                                <th>Kelas</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($recent as $a)
                                                @php $st = $stVal($a->status); @endphp
                                                <tr>
                                                    <td>{{ $a->date ? $a->date->format('d M Y') : '-' }}</td>
                                                    <td>{{ $a->check_in_time ? substr($a->check_in_time, 0, 5) : '-' }}
                                                    </td>
                                                    <td>
                                                        @if ($a->student)
                                                            @if (Route::has('superadmin.school.students.show'))
                                                                <a
                                                                    href="{{ route('superadmin.school.students.show', $a->student->id) }}">{{ $a->student->name }}</a>
                                                            @else
                                                                {{ $a->student->name }}
                                                            @endif
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>{{ $a->classRoom->name ?? '-' }}</td>
                                                    <td><span
                                                            class="badge badge-{{ $statusBadge[$st] ?? 'light' }}">{{ $statuses[$st] ?? $st }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">Kiosk ini belum
                                                        pernah dipakai absen.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Modal buat token baru --}}
    <div class="modal fade" id="tokenModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.school.devices.regenerate-token', $device->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Buat Token Baru</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            Token lama langsung tidak berlaku. Kiosk yang sedang dipakai akan terputus sampai token baru
                            dimasukkan.
                        </div>
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="500" required
                                placeholder="Contoh: tablet hilang / diganti"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-info">Buat Token Baru</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('pages.superadmin.school._delete')
@endsection

@push('scripts')
    <script>
        $(function() {
            $('#tokenModal').appendTo('body');

            $('#copyToken').on('click', function() {
                const input = document.getElementById('deviceToken');
                input.select();
                const done = () => $(this).html('<i class="fas fa-check"></i> Tersalin');
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(input.value).then(done, () => {
                        document.execCommand('copy');
                        done();
                    });
                } else {
                    document.execCommand('copy');
                    done();
                }
            });
        });
    </script>
@endpush
