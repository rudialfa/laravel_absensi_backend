@extends('layouts.app')

@section('title', 'Kurikulum ' . $item->name)

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.academics.index', ['tab' => 'curricula', 'company_id' => $item->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $item->name }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.school.academics.index', ['tab' => 'curricula']) }}">Kurikulum</a>
                    </div>
                    <div class="breadcrumb-item">{{ $item->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($item->is_active && $siblings->where('is_active', true)->isNotEmpty())
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Ada lebih dari satu kurikulum aktif di tahun ajaran
                        {{ $item->academic_year }} untuk sekolah ini.
                        Aplikasi guru bisa memakai kurikulum yang salah.
                    </div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-8">
                        @forelse ($byGrade as $grade => $rows)
                            <div class="card">
                                <div class="card-header">
                                    <h4>Tingkat {{ $grade }}</h4>
                                    <div class="card-header-action"><span
                                            class="badge badge-light">{{ $rows->sum('jam_per_minggu') }} jam/minggu</span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Mapel</th>
                                                <th>Kode</th>
                                                <th>Kelompok</th>
                                                <th class="text-center">Jam/Minggu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rows as $row)
                                                <tr>
                                                    <td>
                                                        {{ $row->subject->name ?? 'Mapel #' . $row->subject_id }}
                                                        @if ($row->subject && !$row->subject->is_active)
                                                            <span class="badge badge-light">mapel nonaktif</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $row->subject->code ?? '-' }}</td>
                                                    <td>{{ $subjectGroups[$row->subject->kelompok ?? ''] ?? '-' }}</td>
                                                    <td class="text-center">{{ $row->jam_per_minggu }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="card">
                                <div class="card-body text-center text-muted">Belum ada mapel di kurikulum ini.</div>
                            </div>
                        @endforelse
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Info</h4>
                                <div class="card-header-action"><span
                                        class="badge badge-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Sekolah</th>
                                        <td class="text-right">
                                            @if ($item->company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $item->company->id) }}">{{ $item->company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Tahun ajaran</th>
                                        <td class="text-right">{{ $item->academic_year }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jumlah mapel</th>
                                        <td class="text-right">{{ $item->curriculumSubjects->count() }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($item->created_at)->format('d M Y') }}</td>
                                    </tr>
                                </table>
                                @if ($item->description)
                                    <hr>
                                    <p class="mb-0 small">{!! nl2br(e($item->description)) !!}</p>
                                @endif
                            </div>
                        </div>

                        @if ($siblings->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Kurikulum Lain {{ $item->academic_year }}</h4>
                                </div>
                                <div class="card-body">
                                    @foreach ($siblings as $s)
                                        <div class="d-flex justify-content-between mb-1">
                                            <a
                                                href="{{ route('superadmin.school.academics.show', ['curricula', $s->id]) }}">{{ $s->name }}</a>
                                            <span
                                                class="badge badge-{{ $s->is_active ? 'success' : 'secondary' }}">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Kurikulum</h6>
                                <p class="small text-muted">Daftar mapel per tingkat di kurikulum ini ikut terhapus. Mapel,
                                    jadwal, dan nilai tidak terpengaruh.</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal"
                                    data-action="{{ route('superadmin.school.academics.destroy', ['curricula', $item->id]) }}"
                                    data-label="Hapus kurikulum {{ $item->name }}"><i class="fas fa-trash"></i>
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
