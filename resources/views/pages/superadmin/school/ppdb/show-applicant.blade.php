@extends('layouts.app')

@section('title', 'Pendaftar ' . $applicant->full_name)

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $student = $applicant->student;
        $studentUrl =
            $student && Route::has('superadmin.school.students.show')
                ? route('superadmin.school.students.show', $student->id)
                : null;
        $scores = $applicant->testScores->keyBy('jenis');
        $avg = $applicant->testScores->whereNotNull('score')->avg(fn($s) => (float) $s->score);
        $steps = ['pending', 'tes', 'lulus', 'daftar_ulang', 'aktif'];
        $stepIndex = array_search($applicant->status, $steps, true);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.ppdb.index', ['tab' => 'applicants', 'company_id' => $applicant->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $applicant->full_name }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.school.ppdb.index', ['tab' => 'applicants']) }}">Pendaftar</a></div>
                    <div class="breadcrumb-item">#{{ $applicant->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                {{-- Alur PPDB --}}
                <div class="card">
                    <div class="card-body">
                        @if ($applicant->status === 'tidak_lulus')
                            <div class="text-center"><span class="badge badge-danger" style="font-size:14px">Tidak
                                    Lulus</span></div>
                        @else
                            <div class="d-flex justify-content-between text-center">
                                @foreach ($steps as $i => $step)
                                    <div class="flex-fill">
                                        <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center {{ $stepIndex !== false && $i <= $stepIndex ? 'bg-primary text-white' : 'bg-light text-muted' }}"
                                            style="width:34px;height:34px">
                                            {{ $i + 1 }}
                                        </div>
                                        <div
                                            class="text-small mt-1 {{ $i === $stepIndex ? 'font-weight-bold' : 'text-muted' }}">
                                            {{ $statusLabels[$step] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Data Pendaftar</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th>Nama</th>
                                                <td>{{ $applicant->full_name }}</td>
                                            </tr>
                                            <tr>
                                                <th>Jenis kelamin</th>
                                                <td>{{ $applicant->gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Tanggal lahir</th>
                                                <td>{{ $fmtDate($applicant->birth_date) }}{{ $applicant->birth_date ? ' (' . $applicant->birth_date->age . ' th)' : '' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Asal sekolah</th>
                                                <td>{{ $applicant->previous_school ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Asrama</th>
                                                <td>{{ $applicant->wants_boarding ? 'Ingin tinggal di asrama' : 'Tidak' }}
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th>Orang tua</th>
                                                <td>{{ $applicant->parent_name }}</td>
                                            </tr>
                                            <tr>
                                                <th>No. HP</th>
                                                <td>{{ $applicant->parent_phone }}</td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td>{{ $applicant->parent_email ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Mendaftar</th>
                                                <td>{{ optional($applicant->created_at)->format('d M Y H:i') }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                @if ($applicant->notes)
                                    <hr>
                                    <h6 class="small text-muted">Catatan sekolah</h6>
                                    <div class="bg-light rounded p-3">{!! nl2br(e($applicant->notes)) !!}</div>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Hasil Tes</h4>
                                @if ($avg !== null)
                                    <div class="card-header-action"><span class="badge badge-primary">Rata-rata
                                            {{ number_format($avg, 1) }}</span></div>
                                @endif
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tes</th>
                                            <th class="text-center">Nilai</th>
                                            <th>Tanggal</th>
                                            <th>Penguji</th>
                                            <th>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($testTypes as $key => $label)
                                            @php $s = $scores[$key] ?? null; @endphp
                                            <tr class="{{ $s ? '' : 'text-muted' }}">
                                                <td>{{ $label }}</td>
                                                <td class="text-center font-weight-bold">
                                                    {{ $s && $s->score !== null ? rtrim(rtrim(number_format((float) $s->score, 2), '0'), '.') : '-' }}
                                                </td>
                                                <td>{{ $s ? $fmtDate($s->test_date) : '-' }}</td>
                                                <td class="text-small">{{ $s->testedBy->name ?? '-' }}</td>
                                                <td class="text-small">{{ $s->notes ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Status</h4>
                                <div class="card-header-action"><span
                                        class="badge badge-{{ $statusBadge[$applicant->status] ?? 'light' }}">{{ $statusLabels[$applicant->status] ?? $applicant->status }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Sekolah</th>
                                        <td class="text-right">
                                            @if ($applicant->company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $applicant->company->id) }}">{{ $applicant->company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Periode</th>
                                        <td class="text-right">
                                            @if ($applicant->period)
                                                <a
                                                    href="{{ route('superadmin.school.ppdb.show', ['periods', $applicant->period->id]) }}">{{ $applicant->period->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Data murid</th>
                                        <td class="text-right">
                                            @if ($student)
                                                @if ($studentUrl)
                                                    <a href="{{ $studentUrl }}">{{ $student->name }}</a>
                                                @else
                                                    {{ $student->name }}
                                                @endif
                                                <div class="text-small text-muted">NIS {{ $student->nis }} ·
                                                    {{ $student->classRoom->name ?? '-' }}</div>
                                            @else
                                                <span class="text-muted">Belum dikonversi</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Pendaftar</h6>
                                <p class="small text-muted">Untuk pendaftaran ganda atau data palsu. Nilai tes ikut
                                    terhapus.{{ $student ? ' Data murid hasil konversi TIDAK ikut terhapus.' : '' }}</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal"
                                    data-action="{{ route('superadmin.school.ppdb.destroy', ['applicants', $applicant->id]) }}"
                                    data-label="Hapus pendaftar {{ $applicant->full_name }}"><i class="fas fa-trash"></i>
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
