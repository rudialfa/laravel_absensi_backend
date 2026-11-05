@extends('layouts.app')

@section('title', 'PPDB & Mutasi')

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $tabUrl = fn($key, $extra = []) => route(
            'superadmin.school.ppdb.index',
            array_filter(
                ['tab' => $key, 'company_id' => request('company_id')] + $extra,
                fn($v) => $v !== null && $v !== '',
            ),
        );
        $hasFilter = collect(request()->except(['tab', 'page']))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();
        $studentUrl = fn($id) => Route::has('superadmin.school.students.show')
            ? route('superadmin.school.students.show', $id)
            : null;
        $delBtn = fn(
            $tabKey,
            $id,
            $label,
            $note = '',
        ) => '<button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" data-toggle="modal" data-target="#schoolActionModal"' .
            ' data-action="' .
            e(route('superadmin.school.ppdb.destroy', [$tabKey, $id])) .
            '" data-label="' .
            e('Hapus ' . $label) .
            '" data-note="' .
            e($note) .
            '"><i class="fas fa-trash"></i></button>';
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>PPDB &amp; Mutasi</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring Sekolah</div>
                    <div class="breadcrumb-item">PPDB &amp; Mutasi</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">Penerimaan murid baru (periode, pendaftar, hasil tes) dan riwayat murid pindah
                    masuk/keluar di semua sekolah.</p>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ $tabUrl($key) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>

                @if ($tab === 'periods' && $multiActive->isNotEmpty())
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Ada sekolah dengan lebih dari satu periode PPDB aktif:
                        @foreach ($multiActive as $cid)
                            <a href="{{ $tabUrl('periods', ['company_id' => $cid]) }}"
                                class="alert-link">{{ $companyNames[$cid] ?? '#' . $cid }}</a>
                            @if (!$loop->last)
                                ,
                            @endif
                        @endforeach
                    </div>
                @endif

                @if ($tab === 'applicants')
                    @php $totalApplicants = $byStatus->sum(); @endphp
                    <div class="row">
                        @foreach ($statusLabels as $value => $label)
                            <div class="col-6 col-md-4 col-xl-2">
                                <a href="{{ $tabUrl('applicants', request()->only(['period_id', 'search']) + ['status' => $value]) }}"
                                    class="text-decoration-none">
                                    <div
                                        class="card mb-3 {{ request('status') === $value ? 'border border-' . ($statusBadge[$value] ?? 'light') : '' }}">
                                        <div class="card-body py-3 text-center">
                                            <div class="h4 mb-0 text-{{ $statusBadge[$value] ?? 'dark' }}">
                                                {{ number_format((int) ($byStatus[$value] ?? 0)) }}</div>
                                            <div class="text-small text-muted">{{ $label }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @elseif ($tab === 'mutations')
                    <div class="row">
                        @foreach ($mutationTypes as $value => $label)
                            <div class="col-6 col-lg-3">
                                <a href="{{ $tabUrl('mutations', ['type' => $value]) }}" class="text-decoration-none">
                                    <div class="card card-statistic-1">
                                        <div class="card-icon bg-{{ $value === 'masuk' ? 'success' : 'warning' }}"><i
                                                class="fas fa-{{ $value === 'masuk' ? 'sign-in-alt' : 'sign-out-alt' }}"></i>
                                        </div>
                                        <div class="card-wrap">
                                            <div class="card-header">
                                                <h4>{{ $label }}</h4>
                                            </div>
                                            <div class="card-body" style="font-size:18px">
                                                {{ number_format((int) ($byType[$value] ?? 0)) }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.school.ppdb.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="this.form.period_id && (this.form.period_id.value=''); this.form.submit()">
                                        <option value="">Semua Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @if ($tab === 'periods')
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua</option>
                                            <option value="open" @selected(request('status') === 'open')>Sedang dibuka</option>
                                            <option value="upcoming" @selected(request('status') === 'upcoming')>Akan dibuka</option>
                                            <option value="closed" @selected(request('status') === 'closed')>Sudah tutup</option>
                                        </select>
                                    </div>
                                @elseif ($tab === 'applicants')
                                    <div class="col-md-2 mb-3">
                                        <select name="period_id" class="form-control" @disabled($periodOptions->isEmpty())>
                                            <option value="">
                                                {{ $periodOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Periode' }}
                                            </option>
                                            @foreach ($periodOptions as $p)
                                                <option value="{{ $p->id }}" @selected((string) request('period_id') === (string) $p->id)>
                                                    {{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua Status</option>
                                            @foreach ($statusLabels as $v => $l)
                                                <option value="{{ $v }}" @selected(request('status') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="boarding" class="form-control">
                                            <option value="">Asrama &amp; non</option>
                                            <option value="1" @selected(request('boarding') === '1')>Ingin asrama</option>
                                        </select>
                                    </div>
                                @else
                                    <div class="col-md-2 mb-3">
                                        <select name="type" class="form-control">
                                            <option value="">Masuk &amp; Keluar</option>
                                            @foreach ($mutationTypes as $v => $l)
                                                <option value="{{ $v }}" @selected(request('type') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3"><input type="date" name="date_from"
                                            value="{{ request('date_from') }}" class="form-control"
                                            title="Dari tanggal efektif"></div>
                                    <div class="col-md-2 mb-3"><input type="date" name="date_to"
                                            value="{{ request('date_to') }}" class="form-control"
                                            title="Sampai tanggal efektif"></div>
                                @endif

                                <div class="col-md mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control"
                                        placeholder="{{ ['periods' => 'Nama periode', 'applicants' => 'Nama / orang tua / HP', 'mutations' => 'Nama / NIS / sekolah / no. surat'][$tab] }}">
                                </div>
                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.school.ppdb.export', request()->query() + ['tab' => $tab]) }}"
                                        class="btn btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.school.ppdb.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">

                                {{-- ---------- PERIODE ---------- --}}
                                @if ($tab === 'periods')
                                    <thead>
                                        <tr>
                                            <th>Periode</th>
                                            <th>Sekolah</th>
                                            <th>Pendaftaran</th>
                                            <th style="width:170px">Kuota</th>
                                            <th class="text-center">Menunggu</th>
                                            <th class="text-center">Diterima</th>
                                            <th class="text-center">Jadi Murid</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $p)
                                            @php
                                                $open =
                                                    $p->is_active &&
                                                    $p->registration_start &&
                                                    $p->registration_end &&
                                                    today()->between($p->registration_start, $p->registration_end);
                                                $fill = $p->quota
                                                    ? min(100, round(($p->accepted_count / $p->quota) * 100))
                                                    : null;
                                            @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.school.ppdb.show', ['periods', $p->id]) }}"
                                                        class="font-weight-bold">{{ $p->name }}</a>
                                                    <div class="text-small text-muted">{{ $p->academic_year }}</div>
                                                </td>
                                                <td class="text-small">{{ $companyNames[$p->company_id] ?? '-' }}</td>
                                                <td class="text-nowrap">
                                                    {{ $fmtDate($p->registration_start) }} –
                                                    {{ $fmtDate($p->registration_end) }}
                                                    <div>
                                                        @if ($open)
                                                            <span class="badge badge-success">sedang dibuka</span>
                                                        @elseif ($p->registration_start && $p->registration_start->gt(today()))
                                                            <span class="badge badge-info">akan dibuka</span>
                                                        @else
                                                            <span class="badge badge-light">tutup</span>
                                                        @endif
                                                        @unless ($p->is_active)
                                                            <span class="badge badge-secondary">nonaktif</span>
                                                        @endunless
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($p->quota)
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-fill mr-2" style="height:8px">
                                                                <div class="progress-bar bg-{{ $fill >= 100 ? 'danger' : 'primary' }}"
                                                                    style="width:{{ $fill }}%"></div>
                                                            </div>
                                                            <span
                                                                class="text-small">{{ $p->accepted_count }}/{{ $p->quota }}</span>
                                                        </div>
                                                        @if ($p->accepted_count > $p->quota)
                                                            <div class="text-small text-danger">melebihi kuota</div>
                                                        @endif
                                                    @else
                                                        <span class="text-muted text-small">Tanpa batas</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $p->waiting_count ?: '-' }}</td>
                                                <td class="text-center">{{ $p->accepted_count ?: '-' }}</td>
                                                <td class="text-center">{{ $p->enrolled_count ?: '-' }}</td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.ppdb.show', ['periods', $p->id]) }}"
                                                        class="btn btn-sm btn-outline-primary"><i
                                                            class="fas fa-eye"></i></a>
                                                    @if (!$p->applicants_count)
                                                        {!! $delBtn('periods', $p->id, 'periode ' . $p->name) !!}
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Belum ada periode
                                                    PPDB.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- PENDAFTAR ---------- --}}
                                @elseif ($tab === 'applicants')
                                    <thead>
                                        <tr>
                                            <th>Pendaftar</th>
                                            <th>Periode</th>
                                            <th>Orang Tua</th>
                                            <th class="text-center">Tes</th>
                                            <th>Status</th>
                                            <th>Daftar</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $a)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.school.ppdb.show', ['applicants', $a->id]) }}"
                                                        class="font-weight-bold">{{ $a->full_name }}</a>
                                                    <div class="text-small text-muted">{{ $a->gender }} ·
                                                        {{ $fmtDate($a->birth_date) }}{{ $a->previous_school ? ' · ' . $a->previous_school : '' }}
                                                    </div>
                                                    @if ($a->wants_boarding)
                                                        <span class="badge badge-info">ingin asrama</span>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $a->period->name ?? '-' }}<div
                                                        class="text-muted">{{ $companyNames[$a->company_id] ?? '' }}</div>
                                                </td>
                                                <td class="text-small">{{ $a->parent_name }}<div class="text-muted">
                                                        {{ $a->parent_phone }}</div>
                                                </td>
                                                <td class="text-center">
                                                    @if ($a->test_scores_count)
                                                        {{ number_format((float) $a->avg_score, 1) }}<div
                                                            class="text-small text-muted">{{ $a->test_scores_count }} tes
                                                        </div>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $statusBadge[$a->status] ?? 'light' }}">{{ $statusLabels[$a->status] ?? $a->status }}</span>
                                                    @if ($a->student)
                                                        <div class="text-small">
                                                            @if ($studentUrl($a->student->id))
                                                                <a
                                                                    href="{{ $studentUrl($a->student->id) }}">{{ $a->student->name }}</a>
                                                            @else
                                                                {{ $a->student->name }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ optional($a->created_at)->format('d M Y') }}
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.ppdb.show', ['applicants', $a->id]) }}"
                                                        class="btn btn-sm btn-outline-primary"><i
                                                            class="fas fa-eye"></i></a>
                                                    {!! $delBtn(
                                                        'applicants',
                                                        $a->id,
                                                        'pendaftar ' . $a->full_name,
                                                        'Nilai tes ikut terhapus.' . ($a->student_id ? ' Data murid hasil konversinya TIDAK ikut terhapus.' : ''),
                                                    ) !!}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Belum ada
                                                    pendaftar.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- MUTASI ---------- --}}
                                @else
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Murid</th>
                                            <th>Jenis</th>
                                            <th>Asal / Tujuan</th>
                                            <th>Surat</th>
                                            <th>Diproses</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $m)
                                            <tr>
                                                <td class="text-nowrap">{{ $fmtDate($m->effective_date) }}</td>
                                                <td>
                                                    @if ($m->student)
                                                        @if ($studentUrl($m->student->id))
                                                            <a
                                                                href="{{ $studentUrl($m->student->id) }}">{{ $m->student->name }}</a>
                                                        @else
                                                            {{ $m->student->name }}
                                                        @endif
                                                        <div class="text-small text-muted">NIS {{ $m->student->nis }} ·
                                                            {{ $companyNames[$m->company_id] ?? '' }}</div>
                                                        @if ($m->type === 'keluar' && $m->student->is_active)
                                                            <span class="badge badge-warning"
                                                                title="Sudah mutasi keluar tapi murid masih aktif">masih
                                                                aktif</span>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $m->type === 'masuk' ? 'success' : 'warning' }}">{{ $mutationTypes[$m->type] ?? $m->type }}</span>
                                                </td>
                                                <td>
                                                    {{ $m->type === 'masuk' ? $m->origin_school : $m->destination_school }}
                                                    @if ($m->reason)
                                                        <div class="text-small text-muted">
                                                            {{ \Illuminate\Support\Str::limit($m->reason, 60) }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-small">
                                                    {{ $m->letter_number ?: '-' }}
                                                    @if ($m->document_path)
                                                        <div><a href="{{ $m->document_url }}" target="_blank"><i
                                                                    class="fas fa-paperclip"></i> Dokumen</a></div>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $m->processedBy->name ?? '-' }}</td>
                                                <td class="text-right">
                                                    {!! $delBtn(
                                                        'mutations',
                                                        $m->id,
                                                        'mutasi ' . ($m->student->name ?? ''),
                                                        'Dokumen ikut terhapus.' . ($m->type === 'keluar' ? ' Murid TIDAK otomatis diaktifkan kembali.' : ''),
                                                    ) !!}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Belum ada data
                                                    mutasi.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                @endif
                            </table>
                        </div>
                    </div>

                    @if ($items->hasPages())
                        <div class="card-footer text-right">{{ $items->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
