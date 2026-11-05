@extends('layouts.app')

@section('title', 'Periode ' . $period->name)

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $total = $applicants->count();
        $accepted = (int) (($byStatus['lulus'] ?? 0) + ($byStatus['daftar_ulang'] ?? 0) + ($byStatus['aktif'] ?? 0));
        $open =
            $period->is_active &&
            $period->registration_start &&
            $period->registration_end &&
            today()->between($period->registration_start, $period->registration_end);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.ppdb.index', ['tab' => 'periods', 'company_id' => $period->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $period->name }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.school.ppdb.index') }}">PPDB</a></div>
                    <div class="breadcrumb-item">{{ $period->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    @foreach ([['Pendaftar', $total, 'primary', 'fa-users'], ['Diterima', $accepted . ($period->quota ? ' / ' . $period->quota : ''), $period->quota && $accepted > $period->quota ? 'danger' : 'success', 'fa-user-check'], ['Jadi Murid', (int) ($byStatus['aktif'] ?? 0), 'info', 'fa-user-graduate'], ['Ingin Asrama', $boarding, 'secondary', 'fa-bed']] as [$label, $value, $color, $icon])
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
                        <div class="card">
                            <div class="card-header">
                                <h4>Pendaftar</h4>
                                <div class="card-header-action">
                                    <a href="{{ route('superadmin.school.ppdb.export', ['tab' => 'applicants', 'company_id' => $period->company_id, 'period_id' => $period->id]) }}"
                                        class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> CSV</a>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>L/P</th>
                                                <th>Orang Tua</th>
                                                <th class="text-center">Rata-rata Tes</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($applicants as $a)
                                                <tr>
                                                    <td><a
                                                            href="{{ route('superadmin.school.ppdb.show', ['applicants', $a->id]) }}">{{ $a->full_name }}</a>
                                                        @if ($a->wants_boarding)
                                                            <span class="badge badge-info">asrama</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $a->gender }}</td>
                                                    <td class="text-small">{{ $a->parent_name }} · {{ $a->parent_phone }}
                                                    </td>
                                                    <td class="text-center">
                                                        {{ $a->avg_score !== null ? number_format((float) $a->avg_score, 1) : '-' }}
                                                    </td>
                                                    <td><span
                                                            class="badge badge-{{ $statusBadge[$a->status] ?? 'light' }}">{{ $statusLabels[$a->status] ?? $a->status }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">Belum ada
                                                        pendaftar.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Info Periode</h4>
                                <div class="card-header-action">
                                    @if ($open)
                                    <span class="badge badge-success">Sedang dibuka</span>@else<span
                                            class="badge badge-light">Tutup</span>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Sekolah</th>
                                        <td class="text-right">
                                            @if ($period->company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $period->company->id) }}">{{ $period->company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Tahun ajaran</th>
                                        <td class="text-right">{{ $period->academic_year }}</td>
                                    </tr>
                                    <tr>
                                        <th>Buka</th>
                                        <td class="text-right">{{ $fmtDate($period->registration_start) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tutup</th>
                                        <td class="text-right">{{ $fmtDate($period->registration_end) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Kuota</th>
                                        <td class="text-right">{{ $period->quota ?? 'Tanpa batas' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td class="text-right">{{ $period->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Per Status</h4>
                            </div>
                            <div class="card-body">
                                @foreach ($statusLabels as $v => $l)
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><span class="badge badge-{{ $statusBadge[$v] }}">&nbsp;</span>
                                            {{ $l }}</span>
                                        <span>{{ (int) ($byStatus[$v] ?? 0) }}</span>
                                    </div>
                                @endforeach
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span>Laki-laki</span><span>{{ (int) ($byGender['L'] ?? 0) }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Perempuan</span><span>{{ (int) ($byGender['P'] ?? 0) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Periode</h6>
                                @if ($total)
                                    <p class="small text-muted mb-0">Periode yang sudah punya pendaftar tidak bisa dihapus
                                        (semua pendaftar & nilai tes akan ikut terhapus).</p>
                                @else
                                    <p class="small text-muted">Periode ini belum punya pendaftar.</p>
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                        data-target="#schoolActionModal"
                                        data-action="{{ route('superadmin.school.ppdb.destroy', ['periods', $period->id]) }}"
                                        data-label="Hapus periode {{ $period->name }}"><i class="fas fa-trash"></i>
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
