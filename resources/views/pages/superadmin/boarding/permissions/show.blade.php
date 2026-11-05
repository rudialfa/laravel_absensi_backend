@extends('layouts.app')

@section('title', 'Izin Asrama #' . $item->id)

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $st = $item->student;
        $room = $st?->activeRoomAssignment?->room;
        $studentUrl =
            $st && Route::has('superadmin.school.students.show')
                ? route('superadmin.school.students.show', $st->id)
                : null;
        [$stateLabel, $stateBadge] = $state;
        $duration =
            $item->tanggal_keluar && $item->tanggal_kembali_rencana
                ? $item->tanggal_keluar->diffInDays($item->tanggal_kembali_rencana) + 1
                : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.boarding.permissions.index', ['company_id' => $item->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $jenis[$item->jenis] ?? $item->jenis }} – {{ $st->name ?? 'Santri' }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.boarding.permissions.index') }}">Izin
                            Asrama</a></div>
                    <div class="breadcrumb-item">#{{ $item->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($stateBadge === 'danger')
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Santri ini
                        <b>{{ strtolower($stateLabel) }}</b> dari rencana kembali dan belum ditandai kembali ke pondok.
                    </div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Rincian Izin</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $statusBadge[$item->status] ?? 'light' }}">{{ $statusLabels[$item->status] ?? $item->status }}</span>
                                    <span class="badge badge-{{ $stateBadge }}">{{ $stateLabel }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col">
                                        <div class="text-small text-muted">Keluar</div>
                                        <div class="h6 mb-0">{{ $fmtDate($item->tanggal_keluar) }}</div>
                                    </div>
                                    <div class="col">
                                        <div class="text-small text-muted">Rencana kembali</div>
                                        <div class="h6 mb-0">{{ $fmtDate($item->tanggal_kembali_rencana) }}</div>
                                    </div>
                                    <div class="col">
                                        <div class="text-small text-muted">Kembali aktual</div>
                                        <div
                                            class="h6 mb-0 {{ $item->tanggal_kembali_aktual ? 'text-success' : 'text-muted' }}">
                                            {{ $fmtDate($item->tanggal_kembali_aktual) }}</div>
                                    </div>
                                    <div class="col">
                                        <div class="text-small text-muted">Lama izin</div>
                                        <div class="h6 mb-0">{{ $duration ? $duration . ' hari' : '-' }}</div>
                                    </div>
                                </div>
                                <h6 class="small text-muted">Alasan</h6>
                                <div class="bg-light rounded p-3 mb-3">{!! nl2br(e($item->alasan ?: '-')) !!}</div>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:160px">Penjemput</th>
                                        <td>{{ $item->nama_penjemput ?: '-' }}{{ $item->hubungan_penjemput ? ' (' . $item->hubungan_penjemput . ')' : '' }}
                                            {{ $item->kontak_penjemput ? '· ' . $item->kontak_penjemput : '' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Diajukan oleh</th>
                                        <td>{{ $item->submittedBy->name ?? '-' }} <span
                                                class="text-small text-muted">{{ $item->submittedBy->phone ?? '' }}</span>
                                            · {{ optional($item->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Diproses oleh</th>
                                        <td>{{ $item->reviewedBy->name ?? 'Belum diproses' }}{{ $item->reviewed_at ? ' · ' . $item->reviewed_at->format('d M Y H:i') : '' }}
                                        </td>
                                    </tr>
                                    @if ($item->catatan_review)
                                        <tr>
                                            <th>Catatan</th>
                                            <td>{{ $item->catatan_review }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Izin Santri Ini</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        @forelse ($history as $h)
                                            <tr>
                                                <td><a
                                                        href="{{ route('superadmin.boarding.permissions.show', $h->id) }}">{{ $fmtDate($h->tanggal_keluar) }}</a>
                                                </td>
                                                <td>{{ $jenis[$h->jenis] ?? $h->jenis }}</td>
                                                <td>s/d {{ $fmtDate($h->tanggal_kembali_rencana) }}</td>
                                                <td><span
                                                        class="badge badge-{{ $statusBadge[$h->status] ?? 'light' }}">{{ $statusLabels[$h->status] ?? $h->status }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="text-center text-muted py-3">Tidak ada izin lain.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Santri</h4>
                            </div>
                            <div class="card-body">
                                @if ($st)
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th>Nama</th>
                                            <td class="text-right">
                                                @if ($studentUrl)
                                                    <a href="{{ $studentUrl }}">{{ $st->name }}</a>
                                                @else
                                                    {{ $st->name }}
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>NIS</th>
                                            <td class="text-right">{{ $st->nis }}</td>
                                        </tr>
                                        <tr>
                                            <th>Kelas</th>
                                            <td class="text-right">{{ $st->classRoom->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Kamar</th>
                                            <td class="text-right">
                                                {{ $room ? ($room->dormitory->name ?? '') . ' · ' . $room->name : 'Belum ada' }}
                                            </td>
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
                                    @if ($st->guardians->isNotEmpty())
                                        <hr>
                                        <div class="text-small text-muted mb-1">Wali terdaftar</div>
                                        @foreach ($st->guardians as $g)
                                            <div>{{ $g->name }} <span
                                                    class="text-small text-muted">{{ $g->phone }}</span></div>
                                        @endforeach
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Pengajuan</h6>
                                <p class="small text-muted">Untuk pengajuan ganda atau salah input.</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal"
                                    data-action="{{ route('superadmin.boarding.permissions.destroy', $item->id) }}"
                                    data-label="Hapus izin {{ $st->name ?? '' }}"><i class="fas fa-trash"></i>
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
