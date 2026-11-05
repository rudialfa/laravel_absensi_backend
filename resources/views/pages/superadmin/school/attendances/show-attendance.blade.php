@extends('layouts.app')

@section('title', 'Detail Absen Murid')

@section('main')
    @php
        $stVal = fn($s) => $s instanceof \BackedEnum ? $s->value : $s;
        $st = $stVal($item->status);
        $student = $item->student;
        $studentUrl =
            $student && Route::has('superadmin.school.students.show')
                ? route('superadmin.school.students.show', $student->id)
                : null;
        $classUrl =
            $item->classRoom && Route::has('superadmin.school.classes.show')
                ? route('superadmin.school.classes.show', $item->classRoom->id)
                : null;
        $deviceUrl =
            $item->device && Route::has('superadmin.school.devices.show')
                ? route('superadmin.school.devices.show', $item->device->id)
                : null;
        $date = \Carbon\Carbon::parse($item->date);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.attendances.index', ['tab' => 'attendances', 'company_id' => $item->company_id, 'date_from' => $date->toDateString(), 'date_to' => $date->toDateString()]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Absen {{ $student->name ?? 'Murid' }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.school.attendances.index') }}">Absen &amp;
                            Izin Murid</a></div>
                    <div class="breadcrumb-item">#{{ $item->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $date->translatedFormat('l, d F Y') }}</h4>
                                <div class="card-header-action">
                                    <span class="badge badge-{{ $badge[$st] ?? 'light' }}"
                                        style="font-size:14px">{{ $statuses[$st] ?? $st }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th style="width:140px">Jam absen</th>
                                                <td>{{ $item->check_in_time ? substr($item->check_in_time, 0, 8) : '-' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Batas terlambat</th>
                                                <td>{{ substr($lateThreshold, 0, 5) }} <span
                                                        class="text-small text-muted">(berlaku semua sekolah)</span></td>
                                            </tr>
                                            <tr>
                                                <th>Kelas saat absen</th>
                                                <td>
                                                    @if ($item->classRoom)
                                                        @if ($classUrl)
                                                            <a href="{{ $classUrl }}">{{ $item->classRoom->name }}</a>
                                                        @else
                                                            {{ $item->classRoom->name }}
                                                        @endif
                                                        <span
                                                            class="text-small text-muted">{{ $item->classRoom->academic_year }}</span>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Wali kelas</th>
                                                <td>{{ $item->classRoom->homeroomTeacher->name ?? '-' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th style="width:120px">Sumber</th>
                                                <td>
                                                    @if ($item->device)
                                                        <i class="fas fa-tablet-alt"></i>
                                                        @if ($deviceUrl)
                                                            <a href="{{ $deviceUrl }}">{{ $item->device->name }}</a>
                                                        @else
                                                            {{ $item->device->name }}
                                                        @endif
                                                    @elseif ($item->recordedBy)
                                                        <i class="fas fa-user"></i> Manual oleh
                                                        {{ $item->recordedBy->name }}
                                                    @else
                                                        <span class="text-muted">Tidak tercatat</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Dicatat</th>
                                                <td>{{ optional($item->created_at)->format('d M Y H:i') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Diubah</th>
                                                <td>
                                                    @if ($item->updated_at && $item->created_at && $item->updated_at->ne($item->created_at))
                                                        {{ $item->updated_at->format('d M Y H:i') }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Catatan</th>
                                                <td>{{ $item->notes ?: '-' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Foto Bukti</h4>
                            </div>
                            <div class="card-body text-center">
                                @if ($photoUrl)
                                    <a href="{{ $photoUrl }}" target="_blank">
                                        <img src="{{ $photoUrl }}" alt="Foto absen" class="img-fluid rounded border"
                                            style="max-height:420px"
                                            onerror="this.closest('a').outerHTML='<p class=&quot;text-danger mb-0&quot;>File foto tidak ditemukan di server.</p>'">
                                    </a>
                                    <div class="text-small text-muted mt-2">{{ $item->photo_evidence }}</div>
                                @else
                                    <p class="text-muted mb-0">Tidak ada foto.</p>
                                @endif
                            </div>
                        </div>

                        {{-- 14 hari terakhir murid ini --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>14 Hari Sampai Tanggal Ini</h4>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap">
                                    @for ($i = 0; $i < 14; $i++)
                                        @php
                                            $d = $historyFrom->copy()->addDays($i);
                                            $h = $history[$d->toDateString()] ?? null;
                                            $hs = $h ? $stVal($h->status) : null;
                                        @endphp
                                        <div class="text-center mr-2 mb-2" style="width:52px"
                                            title="{{ $d->translatedFormat('l, d M') }}{{ $hs ? ': ' . ($statuses[$hs] ?? $hs) : '' }}">
                                            <div class="text-small {{ $d->isSunday() ? 'text-danger' : 'text-muted' }}">
                                                {{ $d->translatedFormat('D') }}</div>
                                            <div
                                                class="rounded py-1 {{ $hs ? 'bg-' . ($badge[$hs] ?? 'light') . ' text-white' : 'bg-light' }} {{ $d->isSameDay($date) ? 'border border-dark' : '' }}">
                                                {{ $d->format('d') }}
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                                <div class="text-small text-muted">
                                    @foreach ($statuses as $v => $l)
                                        <span class="badge badge-{{ $badge[$v] ?? 'light' }}">&nbsp;</span>
                                        {{ \Illuminate\Support\Str::before($l, ' (') }} &nbsp;
                                    @endforeach
                                    <span class="badge badge-light">&nbsp;</span> Tidak ada catatan
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Murid</h4>
                            </div>
                            <div class="card-body">
                                @if ($student)
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th>Nama</th>
                                            <td class="text-right">
                                                @if ($studentUrl)
                                                    <a href="{{ $studentUrl }}">{{ $student->name }}</a>
                                                @else
                                                    {{ $student->name }}
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>NIS</th>
                                            <td class="text-right">{{ $student->nis }}</td>
                                        </tr>
                                        <tr>
                                            <th>Kelas sekarang</th>
                                            <td class="text-right">{{ $student->classRoom->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Sekolah</th>
                                            <td class="text-right">
                                                @if ($item->company)
                                                    <a
                                                        href="{{ route('superadmin.tenants.show', $item->company->id) }}">{{ $item->company->name }}</a>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                    @if ($student->trashed())
                                        <div class="alert alert-light border mt-2 mb-0 small">Murid ini sudah dihapus.</div>
                                    @endif
                                @else
                                    <p class="text-muted mb-0">Data murid tidak ditemukan.</p>
                                @endif
                            </div>
                        </div>

                        @if ($permission)
                            <div class="card">
                                <div class="card-header">
                                    <h4>Pengajuan Izin di Tanggal Ini</h4>
                                </div>
                                <div class="card-body">
                                    <span
                                        class="badge badge-{{ $permission->type === 'sakit' ? 'primary' : 'info' }}">{{ ucfirst($permission->type) }}</span>
                                    <span
                                        class="badge badge-{{ $permBadge[$permission->status] ?? 'light' }}">{{ $permStatuses[$permission->status] ?? $permission->status }}</span>
                                    <p class="small mt-2 mb-2">
                                        {{ \Illuminate\Support\Str::limit($permission->reason, 150) }}</p>
                                    <a href="{{ route('superadmin.school.attendances.show', ['permissions', $permission->id]) }}"
                                        class="btn btn-sm btn-light btn-block">Lihat pengajuan</a>
                                </div>
                            </div>
                        @endif

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Absen</h6>
                                <p class="small text-muted">Untuk absen ganda atau salah murid. Foto ikut terhapus dan murid
                                    bisa diabsen ulang di tanggal ini.</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal"
                                    data-action="{{ route('superadmin.school.attendances.destroy', ['attendances', $item->id]) }}"
                                    data-label="Hapus absen {{ $student->name ?? '' }} ({{ $date->format('d M Y') }})"><i
                                        class="fas fa-trash"></i> Hapus</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
