@extends('layouts.app')

@section('title', 'Detail Izin Murid')

@section('main')
    @php
        $stVal = fn($s) => $s instanceof \BackedEnum ? $s->value : $s;
        $student = $item->student;
        $studentUrl =
            $student && Route::has('superadmin.school.students.show')
                ? route('superadmin.school.students.show', $student->id)
                : null;
        $isImage = $item->attachment && preg_match('/\.(jpe?g|png|webp|gif)$/i', $item->attachment);
        $late = $item->status === 'pending' && $item->date_permission && $item->date_permission->lt(today());
        $attSt = $attendance ? $stVal($attendance->status) : null;
        // Izin disetujui seharusnya tercatat sebagai izin/sakit di absen tanggal tersebut
        $mismatch = $item->status === 'approved' && (!$attendance || $attSt !== $item->type);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.attendances.index', ['tab' => 'permissions', 'company_id' => $student->company_id ?? null]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Izin {{ $student->name ?? 'Murid' }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.school.attendances.index', ['tab' => 'permissions']) }}">Izin /
                            Sakit</a></div>
                    <div class="breadcrumb-item">#{{ $item->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($late)
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Tanggal izin sudah lewat
                        tetapi pengajuan belum diproses sekolah.</div>
                @endif
                @if ($mismatch)
                    <div class="alert alert-light border">
                        <i class="fas fa-info-circle text-warning"></i>
                        Izin sudah disetujui, tetapi absen murid di tanggal ini
                        {{ $attendance ? 'tercatat "' . ($statuses[$attSt] ?? $attSt) . '"' : 'tidak ada' }}, bukan
                        "{{ $permTypes[$item->type] ?? $item->type }}".
                    </div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $item->date_permission ? $item->date_permission->translatedFormat('l, d F Y') : '-' }}
                                </h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $item->type === 'sakit' ? 'primary' : 'info' }}">{{ $permTypes[$item->type] ?? $item->type }}</span>
                                    <span
                                        class="badge badge-{{ $permBadge[$item->status] ?? 'light' }}">{{ $permStatuses[$item->status] ?? $item->status }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6 class="small text-muted">Alasan</h6>
                                <div class="bg-light rounded p-3 mb-3">{!! nl2br(e($item->reason ?: '-')) !!}</div>

                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:150px">Diajukan oleh</th>
                                        <td>
                                            {{ $item->submittedBy->name ?? '-' }}
                                            @if ($item->submittedBy)
                                                <span class="text-small text-muted">·
                                                    {{ $item->submittedBy->phone ?: $item->submittedBy->email }}</span>
                                            @endif
                                            <div class="text-small text-muted">
                                                {{ optional($item->created_at)->format('d M Y H:i') }}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Diproses oleh</th>
                                        <td>
                                            @if ($item->reviewedBy)
                                                {{ $item->reviewedBy->name }} <span
                                                    class="text-small text-muted">({{ $item->reviewedBy->role }})</span>
                                                <div class="text-small text-muted">
                                                    {{ optional($item->reviewed_at)->format('d M Y H:i') }}</div>
                                            @else
                                                <span class="text-muted">Belum diproses</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Lampiran</h4>
                            </div>
                            <div class="card-body text-center">
                                @if ($fileUrl)
                                    @if ($isImage)
                                        <a href="{{ $fileUrl }}" target="_blank"><img src="{{ $fileUrl }}"
                                                alt="Lampiran" class="img-fluid rounded border"
                                                style="max-height:420px"></a>
                                    @else
                                        <a href="{{ $fileUrl }}" target="_blank" class="btn btn-light"><i
                                                class="fas fa-paperclip"></i> Buka lampiran</a>
                                    @endif
                                @else
                                    <p class="text-muted mb-0">Tidak ada lampiran.</p>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Absen di Tanggal Ini</h4>
                            </div>
                            <div class="card-body">
                                @if ($attendance)
                                    <span
                                        class="badge badge-{{ $badge[$attSt] ?? 'light' }}">{{ $statuses[$attSt] ?? $attSt }}</span>
                                    <span class="text-small text-muted ml-2">
                                        {{ $attendance->device->name ?? ($attendance->recordedBy->name ?? '') }}
                                        {{ $attendance->notes ? '· ' . $attendance->notes : '' }}
                                    </span>
                                    <a href="{{ route('superadmin.school.attendances.show', ['attendances', $attendance->id]) }}"
                                        class="btn btn-sm btn-light float-right">Detail absen</a>
                                @else
                                    <p class="text-muted mb-0">Belum ada catatan absen untuk tanggal ini.</p>
                                @endif
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
                                            <th>Kelas</th>
                                            <td class="text-right">{{ $student->classRoom->name ?? 'Belum ada' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Sekolah</th>
                                            <td class="text-right">
                                                @if ($student->company)
                                                    <a
                                                        href="{{ route('superadmin.tenants.show', $student->company->id) }}">{{ $student->company->name }}</a>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Izin Lain Murid Ini</h4>
                            </div>
                            <div class="card-body">
                                @forelse ($history as $h)
                                    <div class="d-flex justify-content-between mb-1">
                                        <a
                                            href="{{ route('superadmin.school.attendances.show', ['permissions', $h->id]) }}">{{ optional($h->date_permission)->format('d M Y') }}</a>
                                        <span>
                                            <span class="text-small text-muted">{{ ucfirst($h->type) }}</span>
                                            <span
                                                class="badge badge-{{ $permBadge[$h->status] ?? 'light' }}">{{ $permStatuses[$h->status] ?? $h->status }}</span>
                                        </span>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Tidak ada.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Pengajuan</h6>
                                <p class="small text-muted">Untuk pengajuan ganda atau salah. Lampiran ikut terhapus. Absen
                                    yang sudah tercatat tidak ikut terhapus.</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal"
                                    data-action="{{ route('superadmin.school.attendances.destroy', ['permissions', $item->id]) }}"
                                    data-label="Hapus izin {{ $student->name ?? '' }}"><i class="fas fa-trash"></i>
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
