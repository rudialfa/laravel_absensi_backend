@extends('layouts.app')

@section('title', "Mutaba'ah " . $santri->name)

@section('main')
    @php
        $Model = \App\Models\MutabaahYaumiyah::class;
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $nilaiColor = fn($k) => match (true) {
            in_array($k, ['A+', 'A', 'A-'], true) => 'success',
            in_array($k, ['B+', 'B'], true) => 'primary',
            in_array($k, ['B-', 'C+', 'C', 'C-'], true) => 'warning',
            default => 'danger',
        };
        $userUrl = Route::has('superadmin.users.show') ? route('superadmin.users.show', $santri->id) : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.boarding.mutabaah.index', ['tab' => 'progress', 'company_id' => $santri->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $santri->name }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.boarding.mutabaah.index') }}">Mutaba'ah</a>
                    </div>
                    <div class="breadcrumb-item">{{ $santri->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    @foreach ([['Posisi Sekarang', $latest ? ($kitab[$latest->kitab] ?? $latest->kitab) . ' Jilid ' . $latest->jilid : '-', 'primary', 'fa-book-open'], ['Halaman Berikutnya', $latest ? 'Hal. ' . $Model::halamanBerikutnya($latest) : '-', 'info', 'fa-bookmark'], ['Total Sesi / % Lanjut', (int) $stats->total . ($stats->total ? ' / ' . round(($stats->lanjut / $stats->total) * 100) . '%' : ''), 'success', 'fa-chart-line'], ['Belum Diparaf', (int) $stats->unsigned, $stats->unsigned ? 'warning' : 'success', 'fa-signature']] as [$label, $value, $color, $icon])
                        <div class="col-6 col-lg-3">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>{{ $label }}</h4>
                                    </div>
                                    <div class="card-body" style="font-size:17px">{{ $value }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Ngaji</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Sesi</th>
                                                <th>Bacaan</th>
                                                <th class="text-center">Nilai</th>
                                                <th>Ustadz</th>
                                                <th>Paraf</th>
                                                <th class="text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($records as $m)
                                                <tr>
                                                    <td class="text-nowrap">
                                                        {{ $m->tanggal ? $m->tanggal->translatedFormat('D, d M Y') : '-' }}
                                                    </td>
                                                    <td>{{ $sesi[$m->sesi] ?? $m->sesi }}</td>
                                                    <td>{{ $m->label_posisi }}@if ($m->catatan)
                                                            <div class="text-small text-muted">{{ $m->catatan }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="text-center"><span
                                                            class="badge badge-{{ $nilaiColor($m->keterangan) }}">{{ $m->keterangan }}</span>
                                                        <div class="text-small">{{ $m->is_lanjut ? 'lanjut' : 'ulang' }}
                                                        </div>
                                                    </td>
                                                    <td class="text-small">{{ $m->ustadz->name ?? '-' }}</td>
                                                    <td class="text-small">{!! $m->signed_by
                                                        ? '<i class="fas fa-signature text-success"></i> ' . e($m->penandatangan->name ?? '')
                                                        : '<span class="text-warning">belum</span>' !!}</td>
                                                    <td class="text-right">
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                            data-toggle="modal" data-target="#schoolActionModal"
                                                            data-action="{{ route('superadmin.boarding.mutabaah.destroy', ['mutabaah', $m->id]) }}"
                                                            data-label="Hapus catatan {{ optional($m->tanggal)->format('d M Y') }} ({{ $sesi[$m->sesi] ?? $m->sesi }})"
                                                            data-note="Halaman berikutnya santri akan dihitung dari catatan terakhir yang tersisa."><i
                                                                class="fas fa-trash"></i></button>
                                                    </td>
                                                </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7" class="text-center text-muted py-3">Belum ada
                                                            catatan.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @if ($records->hasPages())
                                    <div class="card-footer text-right">{{ $records->links() }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Santri</h4>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th>Nama</th>
                                            <td class="text-right">
                                                @if ($userUrl)
                                                    <a href="{{ $userUrl }}">{{ $santri->name }}</a>
                                                @else
                                                    {{ $santri->name }}
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Pesantren</th>
                                            <td class="text-right">{{ $santri->company->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Mulai ngaji</th>
                                            <td class="text-right">{{ $fmtDate($stats->first_date) }}</td>
                                        </tr>
                                        <tr>
                                            <th>Terakhir</th>
                                            <td class="text-right">{{ $fmtDate($stats->last_date) }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h4>Perjalanan per Jilid</h4>
                                </div>
                                <div class="card-body">
                                    @forelse ($perJilid as $j)
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <b>{{ $kitab[$j->kitab] ?? $j->kitab }} Jilid {{ $j->jilid }}</b>
                                                <span class="text-small text-muted">s/d hal. {{ $j->max_hal }}</span>
                                            </div>
                                            <div class="text-small text-muted">
                                                {{ $j->sessions }} sesi ·
                                                {{ $j->sessions ? round(($j->lanjut / $j->sessions) * 100) : 0 }}% lanjut ·
                                                {{ $fmtDate($j->start) }} – {{ $fmtDate($j->end) }}
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted mb-0">Belum ada.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        @include('pages.superadmin.school._delete')
    @endsection
