@extends('layouts.app')

@section('title', 'Detail ' . $tabs[$tab])

@section('main')
    @php
        $Ctrl = \App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminRequestController::class;
        [$start, $end, $time] = $Ctrl::periodOf($tab, $item);
        $fileUrl = $file ? (str_starts_with($file, 'http') ? $file : asset($file)) : null;
        $isImage = $file && preg_match('/\.(jpe?g|png|webp|gif)$/i', $file);
        $days = $start ? \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end ?: $start)) + 1 : null;
        $attStatus = \App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminAttendanceController::STATUSES;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.monitoring.requests.index', ['tab' => $tab]) }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail {{ $tabs[$tab] }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.monitoring.requests.index', ['tab' => $tab]) }}">{{ $tabs[$tab] }}</a>
                    </div>
                    <div class="breadcrumb-item">#{{ $item->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $Ctrl::typeLabel($tab, $item) }}</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $badge[$status] ?? 'secondary' }}">{{ $statusLabels[$status] ?? $status }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:160px">Karyawan</th>
                                        <td>
                                            @if ($item->user && \Illuminate\Support\Facades\Route::has('superadmin.users.show'))
                                                <a
                                                    href="{{ route('superadmin.users.show', $item->user_id) }}">{{ $item->user->name }}</a>
                                            @else
                                                {{ $item->user->name ?? '(user terhapus)' }}
                                            @endif
                                            <div class="text-small text-muted">
                                                {{ collect([$item->user->position ?? null, $item->user->department ?? null, $item->user->phone ?? null])->filter()->implode(' · ') }}
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
                                        <th>Tanggal</th>
                                        <td>
                                            {{ \Carbon\Carbon::parse($start)->translatedFormat('l, d M Y') }}
                                            @if ($end && $end !== $start)
                                                s/d {{ \Carbon\Carbon::parse($end)->translatedFormat('l, d M Y') }}
                                            @endif
                                            @if ($tab !== 'overtimes' && $days > 1)
                                                <span class="badge badge-light">{{ $days }} hari</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($time)
                                        <tr>
                                            <th>Jam</th>
                                            <td>
                                                {{ $time }}
                                                @if ($tab === 'overtimes')
                                                    <span class="badge badge-light">{{ $item->duration_label }}</span>
                                                    @if ($item->is_overnight)
                                                        <span class="badge badge-info">lewat tengah malam</span>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th>Alasan</th>
                                        <td>{!! nl2br(e($item->reason ?: '-')) !!}</td>
                                    </tr>
                                    <tr>
                                        <th>Diajukan</th>
                                        <td>
                                            {{ optional($item->created_at)->format('d M Y H:i') }}
                                            <span
                                                class="text-muted text-small">({{ optional($item->created_at)->diffForHumans() }})</span>
                                        </td>
                                    </tr>
                                </table>

                                @if ($fileUrl)
                                    <div class="mt-3">
                                        <h6 class="small text-muted">Lampiran</h6>
                                        @if ($isImage)
                                            <a href="{{ $fileUrl }}" target="_blank"><img src="{{ $fileUrl }}"
                                                    alt="Lampiran" class="img-fluid rounded border"
                                                    style="max-height:360px"></a>
                                        @else
                                            <a href="{{ $fileUrl }}" target="_blank" class="btn btn-sm btn-light"><i
                                                    class="fas fa-paperclip"></i> Buka lampiran</a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Keputusan HR</h4>
                            </div>
                            <div class="card-body">
                                @if ($status === 'pending')
                                    <p
                                        class="mb-0 {{ $item->created_at && $item->created_at->lt(now()->subDays(3)) ? 'text-danger' : 'text-muted' }}">
                                        <i class="fas fa-hourglass-half"></i> Belum diproses HR sejak
                                        {{ optional($item->created_at)->diffForHumans() }}.
                                    </p>
                                @else
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th style="width:160px">Status</th>
                                            <td><span
                                                    class="badge badge-{{ $badge[$status] ?? 'secondary' }}">{{ $statusLabels[$status] ?? $status }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Oleh</th>
                                            <td>{{ $item->approver->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Pada</th>
                                            <td>{{ $item->approved_at ? \Carbon\Carbon::parse($item->approved_at)->format('d M Y H:i') : '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Catatan HR</th>
                                            <td>{{ $item->approval_note ?: '-' }}</td>
                                        </tr>
                                    </table>
                                @endif
                            </div>
                        </div>

                        @if ($tab === 'overtimes' && $item->attendance)
                            <div class="card">
                                <div class="card-header">
                                    <h4>Absen di Hari Lembur</h4>
                                </div>
                                <div class="card-body">
                                    Masuk
                                    {{ $item->attendance->time_in ? substr($item->attendance->time_in, 0, 5) : '-' }},
                                    pulang
                                    {{ $item->attendance->time_out ? substr($item->attendance->time_out, 0, 5) : '-' }} ·
                                    {{ $attStatus[$item->attendance->status] ?? $item->attendance->status }}
                                    <a href="{{ route('superadmin.monitoring.attendances.show', $item->attendance->id) }}"
                                        class="ml-2">Lihat absen</a>
                                </div>
                            </div>
                        @endif

                        @if ($linkedAttendances->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Absen yang Memakai Izin Ini</h4>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        @foreach ($linkedAttendances as $att)
                                            <tr>
                                                <td class="pl-4"><a
                                                        href="{{ route('superadmin.monitoring.attendances.show', $att->id) }}">{{ optional($att->date)->format('d M Y') }}</a>
                                                </td>
                                                <td>{{ $att->time_in ? substr($att->time_in, 0, 5) : '-' }} –
                                                    {{ $att->time_out ? substr($att->time_out, 0, 5) : '-' }}</td>
                                                <td class="pr-4 text-right">{{ $attStatus[$att->status] ?? $att->status }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $tabs[$tab] }} Lain Tahun Ini</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @forelse ($history as $h)
                                        @php
                                            $hs = $Ctrl::statusOf($tab, $h);
                                            [$hStart, $hEnd] = $Ctrl::periodOf($tab, $h);
                                        @endphp
                                        <tr>
                                            <td class="pl-3 text-small">
                                                <a
                                                    href="{{ route('superadmin.monitoring.requests.show', [$tab, $h->id]) }}">
                                                    {{ \Carbon\Carbon::parse($hStart)->format('d M') }}{{ $hEnd && $hEnd !== $hStart ? ' – ' . \Carbon\Carbon::parse($hEnd)->format('d M') : '' }}
                                                </a>
                                                <div class="text-muted">{{ $Ctrl::typeLabel($tab, $h) }}</div>
                                            </td>
                                            <td class="pr-3 text-right"><span
                                                    class="badge badge-{{ $badge[$hs] ?? 'secondary' }}">{{ $statusLabels[$hs] ?? $hs }}</span>
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
                                <h6 class="text-danger mb-1">Hapus Pengajuan</h6>
                                <p class="small text-muted">
                                    Untuk data ganda atau salah. Lampiran ikut terhapus.
                                    @if ($tab === 'overtimes' && $status === 'approved')
                                        <br><b>Catatan:</b> menit lembur di data absen tidak ikut dikembalikan.
                                    @endif
                                    @if ($tab === 'permissions' && $linkedAttendances->isNotEmpty())
                                        <br><b>Catatan:</b> absen yang memakai izin ini akan kehilangan tautannya.
                                    @endif
                                </p>
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
            <form action="{{ route('superadmin.monitoring.requests.destroy', [$tab, $item->id]) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Hapus {{ $tabs[$tab] }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
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
