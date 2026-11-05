@extends('layouts.app')

@section('title', 'Kesehatan ' . $student->name)

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $studentUrl = Route::has('superadmin.school.students.show')
            ? route('superadmin.school.students.show', $student->id)
            : null;
        $para = fn($v) => nl2br(e($v ?: '-'));
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('superadmin.school.health.index') }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Kesehatan {{ $student->name }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.school.health.index') }}">Kesehatan / UKS</a>
                    </div>
                    <div class="breadcrumb-item">{{ $student->nis }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="alert alert-light border small mb-3">
                    <i class="fas fa-user-shield text-primary"></i> Data pribadi sensitif. Pembukaan halaman ini sudah
                    tercatat di Audit Log. Jangan disebarkan di luar keperluan.
                </div>

                <div class="row">
                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>Murid</h4>
                            </div>
                            <div class="card-body">
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
                                        <td class="text-right">{{ $student->classRoom->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Sekolah</th>
                                        <td class="text-right">{{ $student->company->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Umur</th>
                                        <td class="text-right">
                                            {{ $student->birth_date ? $student->birth_date->age . ' tahun' : '-' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Data Kesehatan</h4>
                            </div>
                            <div class="card-body">
                                @if ($record)
                                    <div class="row text-center mb-3">
                                        <div class="col">
                                            <div class="text-small text-muted">Gol. darah</div>
                                            <div class="h5 mb-0">{{ $record->blood_type ?: '-' }}</div>
                                        </div>
                                        <div class="col">
                                            <div class="text-small text-muted">Tinggi</div>
                                            <div class="h5 mb-0">
                                                {{ $record->height_cm ? (float) $record->height_cm . ' cm' : '-' }}</div>
                                        </div>
                                        <div class="col">
                                            <div class="text-small text-muted">Berat</div>
                                            <div class="h5 mb-0">
                                                {{ $record->weight_kg ? (float) $record->weight_kg . ' kg' : '-' }}</div>
                                        </div>
                                    </div>
                                    @if ($bmi)
                                        <p class="text-small text-muted text-center">IMT {{ $bmi }} (untuk anak,
                                            nilai IMT perlu dibaca dengan grafik pertumbuhan sesuai umur)</p>
                                    @endif
                                    <h6 class="small text-muted">Alergi</h6>
                                    <div
                                        class="bg-light rounded p-2 mb-3 {{ $record->allergies ? 'text-warning font-weight-bold' : '' }}">
                                        {!! $para($record->allergies) !!}</div>
                                    <h6 class="small text-muted">Penyakit kronis</h6>
                                    <div
                                        class="bg-light rounded p-2 mb-3 {{ $record->chronic_conditions ? 'text-danger font-weight-bold' : '' }}">
                                        {!! $para($record->chronic_conditions) !!}</div>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th>Kontak darurat</th>
                                            <td class="text-right">{{ $record->emergency_contact_name ?: '-' }}<div
                                                    class="text-small">{{ $record->emergency_contact_phone }}</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Dicatat</th>
                                            <td class="text-right">{{ $fmtDate($record->recorded_at) }} ·
                                                {{ $record->recordedBy->name ?? '-' }}</td>
                                        </tr>
                                    </table>
                                @else
                                    <p class="text-muted mb-0">Sekolah belum mengisi data kesehatan murid ini.</p>
                                @endif
                            </div>
                        </div>

                        @if ($student->guardians->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Wali</h4>
                                </div>
                                <div class="card-body">
                                    @foreach ($student->guardians as $g)
                                        <div>{{ $g->name }} <span
                                                class="text-small text-muted">{{ $g->phone }}</span></div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Kunjungan UKS ({{ $visits->count() }})</h4>
                            </div>
                            <div class="card-body">
                                @forelse ($visits as $v)
                                    <div class="border-bottom pb-2 mb-3">
                                        <div class="d-flex justify-content-between">
                                            <b>{{ optional($v->visited_at)->translatedFormat('l, d M Y H:i') }}</b>
                                            <span
                                                class="badge badge-{{ $outcomeBadge[$v->outcome] ?? 'light' }}">{{ $outcomes[$v->outcome] ?? $v->outcome }}</span>
                                        </div>
                                        <div class="mt-1"><span class="text-muted text-small">Keluhan:</span>
                                            {{ $v->complaint }}</div>
                                        @if ($v->treatment)
                                            <div><span class="text-muted text-small">Penanganan:</span> {{ $v->treatment }}
                                            </div>
                                        @endif
                                        @if ($v->notes)
                                            <div class="text-small text-muted">{{ $v->notes }}</div>
                                        @endif
                                        <div class="text-small text-muted">Dicatat oleh {{ $v->recordedBy->name ?? '-' }}
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Belum pernah ke UKS.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
