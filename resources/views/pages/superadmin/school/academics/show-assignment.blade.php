@extends('layouts.app')

@section('title', 'Tugas ' . $item->title)

@section('main')
    @php
        $Ctrl = \App\Http\Controllers\Web\SuperAdmin\School\SuperAdminAcademicController::class;
        $studentUrl = fn($id) => Route::has('superadmin.school.students.show')
            ? route('superadmin.school.students.show', $id)
            : null;
        $graded = $submissions->where('status', 'dikoreksi');
        $classSize = $submissions->count() + $missing->count();
        $late = fn($s) => $item->deadline && $s->submitted_at && $s->submitted_at->gt($item->deadline);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.academics.index', ['tab' => 'assignments', 'company_id' => $item->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $item->title }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.school.academics.index', ['tab' => 'assignments']) }}">Tugas</a>
                    </div>
                    <div class="breadcrumb-item">#{{ $item->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    @foreach ([['Mengumpulkan', $submissions->count() . ' / ' . $classSize, 'primary', 'fa-inbox'], ['Sudah Dikoreksi', $graded->count(), 'success', 'fa-check-double'], ['Rata-rata Nilai', $graded->count() ? number_format($graded->avg(fn($s) => (float) $s->nilai), 1) : '-', 'info', 'fa-chart-bar'], ['Belum Mengumpulkan', $missing->count(), $missing->count() ? 'warning' : 'success', 'fa-user-clock']] as [$label, $value, $color, $icon])
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
                                <h4>Jawaban Murid</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Murid</th>
                                                <th>Dikumpulkan</th>
                                                <th>File</th>
                                                <th>Status</th>
                                                <th class="text-center">Nilai</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($submissions as $s)
                                                <tr>
                                                    <td>
                                                        @if ($s->student)
                                                            @if ($studentUrl($s->student->id))
                                                                <a
                                                                    href="{{ $studentUrl($s->student->id) }}">{{ $s->student->name }}</a>
                                                            @else
                                                                {{ $s->student->name }}
                                                            @endif
                                                        @endif
                                                        <div class="text-small text-muted">oleh
                                                            {{ $s->submittedBy->name ?? '-' }}</div>
                                                        @if ($s->notes)
                                                            <div class="text-small">
                                                                "{{ \Illuminate\Support\Str::limit($s->notes, 60) }}"</div>
                                                        @endif
                                                    </td>
                                                    <td class="text-small text-nowrap">
                                                        {{ optional($s->submitted_at)->format('d M Y H:i') }}
                                                        @if ($late($s))
                                                            <div><span class="badge badge-warning">terlambat</span></div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($s->file_path)
                                                            <a href="{{ $Ctrl::fileUrl($s->file_path) }}" target="_blank"
                                                                class="text-small"><i class="fas fa-paperclip"></i>
                                                                {{ \Illuminate\Support\Str::limit($s->file_name ?: basename($s->file_path), 25) }}</a>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge badge-{{ $s->status === 'dikoreksi' ? 'success' : 'warning' }}">{{ $statusLabels[$s->status] ?? $s->status }}</span>
                                                        @if ($s->gradedBy)
                                                            <div class="text-small text-muted">{{ $s->gradedBy->name }}
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        {{ $s->nilai !== null ? rtrim(rtrim(number_format((float) $s->nilai, 2), '0'), '.') : '-' }}
                                                        @if ($s->catatan_guru)
                                                            <i class="fas fa-comment text-muted"
                                                                title="{{ $s->catatan_guru }}"></i>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">Belum ada murid
                                                        yang mengumpulkan.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        @if ($missing->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Belum Mengumpulkan ({{ $missing->count() }})</h4>
                                </div>
                                <div class="card-body">
                                    @foreach ($missing as $m)
                                        <span class="badge badge-light mb-1">{{ $m->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Info Tugas</h4>
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
                                        <th>Kelas</th>
                                        <td class="text-right">{{ $item->classRoom->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Mapel</th>
                                        <td class="text-right">{{ $item->subject->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Guru</th>
                                        <td class="text-right">{{ $item->teacher->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Batas waktu</th>
                                        <td class="text-right">
                                            {{ optional($item->deadline)->format('d M Y H:i') }}
                                            <div
                                                class="text-small {{ $item->deadline && $item->deadline->isPast() ? 'text-muted' : 'text-success' }}">
                                                {{ $item->deadline ? $item->deadline->diffForHumans() : '' }}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($item->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                </table>
                                @if ($item->description)
                                    <hr>
                                    <div class="small">{!! nl2br(e($item->description)) !!}</div>
                                @endif
                                @if ($fileUrl)
                                    <a href="{{ $fileUrl }}" target="_blank" class="btn btn-light btn-block mt-3"><i
                                            class="fas fa-paperclip"></i> {{ $item->file_name ?: 'Lampiran tugas' }}</a>
                                @endif
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Tugas</h6>
                                <p class="small text-muted">Semua jawaban murid ({{ $submissions->count() }}) beserta
                                    file-nya ikut terhapus.</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#schoolActionModal"
                                    data-action="{{ route('superadmin.school.academics.destroy', ['assignments', $item->id]) }}"
                                    data-label="Hapus tugas {{ $item->title }}"><i class="fas fa-trash"></i>
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
