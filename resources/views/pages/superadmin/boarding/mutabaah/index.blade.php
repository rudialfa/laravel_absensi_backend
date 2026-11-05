@extends('layouts.app')

@section('title', "Mutaba'ah & Tahfidz")

@section('main')
    @php
        $Model = \App\Models\MutabaahYaumiyah::class;
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $dateQ = in_array($tab, ['mutabaah', 'tahfidz'], true)
            ? ['date_from' => request('date_from'), 'date_to' => request('date_to')]
            : [];
        $tabUrl = fn($key, $extra = []) => route(
            'superadmin.boarding.mutabaah.index',
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
        $nilaiColor = fn($k) => match (true) {
            in_array($k, ['A+', 'A', 'A-'], true) => 'success',
            in_array($k, ['B+', 'B'], true) => 'primary',
            in_array($k, ['B-', 'C+', 'C', 'C-'], true) => 'warning',
            default => 'danger',
        };
        $delBtn = fn(
            $tabKey,
            $id,
            $label,
        ) => '<button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" data-toggle="modal" data-target="#schoolActionModal"' .
            ' data-action="' .
            e(route('superadmin.boarding.mutabaah.destroy', [$tabKey, $id])) .
            '" data-label="' .
            e('Hapus ' . $label) .
            '">
<i class="fas fa-trash"></i></button>';
        $usesDate = in_array($tab, ['mutabaah', 'tahfidz'], true);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Mutaba'ah &amp; Tahfidz</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Pesantren &amp; Asrama</div>
                    <div class="breadcrumb-item">Mutaba'ah &amp; Tahfidz</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    <b>Mutaba'ah</b>: catatan ngaji harian santri (Iqro / Al-Qur'an) per sesi pagi &amp; sore, beserta nilai
                    dan paraf ustadz.
                    <b>Tahfidz</b>: setoran hafalan murid (ziyadah &amp; muraja'ah).
                    @if ($usesDate)
                        Periode: <b>{{ $from->translatedFormat('d M Y') }} – {{ $to->translatedFormat('d M Y') }}</b>.
                    @endif
                </p>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ $tabUrl($key) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>

                {{-- ================= RINGKASAN ================= --}}
                @if ($tab === 'mutabaah')
                    <div class="row">
                        @foreach ([['Catatan Ngaji', number_format((int) $summary->total), 'primary', 'fa-book-reader', null], ['Lanjut Halaman', $summary->total ? round(($summary->lanjut / $summary->total) * 100) . '%' : '-', 'success', 'fa-forward', ['status' => 'lanjut']], ['Belum Diparaf', number_format((int) $summary->unsigned), $summary->unsigned ? 'warning' : 'success', 'fa-signature', ['status' => 'unsigned']], ['Santri / Ustadz', (int) $summary->santri . ' / ' . (int) $summary->ustadz, 'info', 'fa-users', null]] as [$label, $value, $color, $icon, $filter])
                            <div class="col-6 col-lg-3">
                                @if ($filter)
                                    <a href="{{ $tabUrl('mutabaah', $filter + $dateQ) }}" class="text-decoration-none">
                                @endif
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i>
                                    </div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>{{ $label }}</h4>
                                        </div>
                                        <div class="card-body" style="font-size:18px">{{ $value }}</div>
                                    </div>
                                </div>
                                @if ($filter)
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if ($byNilai->isNotEmpty())
                        <div class="card">
                            <div class="card-body py-3">
                                <span class="text-small text-muted mr-2">Sebaran nilai:</span>
                                @foreach ($nilaiList as $n)
                                    @if (($byNilai[$n] ?? 0) > 0)
                                        <a href="{{ $tabUrl('mutabaah', ['nilai' => $n] + $dateQ) }}"
                                            class="badge badge-{{ $nilaiColor($n) }} mr-1">{{ $n }}:
                                            {{ $byNilai[$n] }}</a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @elseif ($tab === 'progress' && $neverCount)
                    <div class="alert alert-light border">
                        <i class="fas fa-info-circle text-warning"></i> {{ $neverCount }} akun santri aktif belum pernah
                        tercatat mutaba'ah sama sekali.
                    </div>
                @elseif ($tab === 'tahfidz')
                    <div class="row">
                        @foreach ([['Setoran', number_format((int) $summary->total), 'primary', 'fa-quran', null], ['Ziyadah / Muraja\'ah', (int) $summary->ziyadah . ' / ' . (int) $summary->murajaah, 'info', 'fa-layer-group', null], ['Lancar', $summary->total ? round(($summary->lancar / $summary->total) * 100) . '%' : '-', 'success', 'fa-check', ['status' => 'lancar']], ['Ayat Baru Dihafal', number_format((int) $summary->ayat_baru), 'warning', 'fa-star', ['jenis' => 'ziyadah']]] as [$label, $value, $color, $icon, $filter])
                            <div class="col-6 col-lg-3">
                                @if ($filter)
                                    <a href="{{ $tabUrl('tahfidz', $filter + $dateQ) }}" class="text-decoration-none">
                                @endif
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i>
                                    </div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>{{ $label }}</h4>
                                        </div>
                                        <div class="card-body" style="font-size:18px">{{ $value }}</div>
                                    </div>
                                </div>
                                @if ($filter)
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="card">
                    {{-- ================= FILTER ================= --}}
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.boarding.mutabaah.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="this.form.teacher_id && (this.form.teacher_id.value=''); this.form.submit()">
                                        <option value="">Semua Pesantren / Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}{{ $s->type === 'pesantren' ? ' (Pesantren)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($usesDate)
                                    <div class="col-md-2 mb-3"><input type="date" name="date_from"
                                            value="{{ $from->toDateString() }}" class="form-control" title="Dari"></div>
                                    <div class="col-md-2 mb-3"><input type="date" name="date_to"
                                            value="{{ $to->toDateString() }}" class="form-control" title="Sampai"></div>
                                    <div class="col-md-2 mb-3">
                                        <select name="teacher_id" class="form-control" @disabled($teacherOptions->isEmpty())>
                                            <option value="">
                                                {{ $teacherOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua ' . ($tab === 'mutabaah' ? 'Ustadz' : 'Guru') }}
                                            </option>
                                            @foreach ($teacherOptions as $t)
                                                <option value="{{ $t->id }}" @selected((string) request('teacher_id') === (string) $t->id)>
                                                    {{ $t->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control"
                                        placeholder="{{ in_array($tab, ['tahfidz', 'hafalan'], true) ? 'Nama / NIS murid' . ($tab === 'tahfidz' ? ', surah' : '') : 'Nama santri' }}">
                                </div>
                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    @if ($usesDate)
                                        <a href="{{ route('superadmin.boarding.mutabaah.export', request()->query() + ['tab' => $tab, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}"
                                            class="btn btn-outline-success" title="Unduh CSV"><i
                                                class="fas fa-file-excel"></i></a>
                                    @endif
                                </div>
                            </div>

                            <div class="form-row">
                                @if (in_array($tab, ['mutabaah', 'progress'], true))
                                    <div class="col-md-2 mb-3">
                                        <select name="kitab" class="form-control form-control-sm">
                                            <option value="">Iqro &amp; Al-Qur'an</option>
                                            @foreach ($kitab as $v => $l)
                                                <option value="{{ $v }}" @selected(request('kitab') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($tab === 'mutabaah')
                                    <div class="col-md-2 mb-3">
                                        <select name="sesi" class="form-control form-control-sm">
                                            <option value="">Pagi &amp; Sore</option>
                                            @foreach ($sesi as $v => $l)
                                                <option value="{{ $v }}" @selected(request('sesi') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control form-control-sm">
                                            <option value="">Semua</option>
                                            <option value="lanjut" @selected(request('status') === 'lanjut')>Lanjut halaman</option>
                                            <option value="ulang" @selected(request('status') === 'ulang')>Mengulang</option>
                                            <option value="unsigned" @selected(request('status') === 'unsigned')>Belum diparaf</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="nilai" class="form-control form-control-sm">
                                            <option value="">Semua Nilai</option>
                                            @foreach ($nilaiList as $n)
                                                <option value="{{ $n }}" @selected(request('nilai') === $n)>
                                                    {{ $n }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif ($tab === 'tahfidz')
                                    <div class="col-md-2 mb-3">
                                        <select name="jenis" class="form-control form-control-sm">
                                            <option value="">Ziyadah &amp; Muraja'ah</option>
                                            @foreach ($tahfidzJenis as $v => $l)
                                                <option value="{{ $v }}" @selected(request('jenis') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control form-control-sm">
                                            <option value="">Semua Status</option>
                                            @foreach ($tahfidzStatus as $v => $l)
                                                <option value="{{ $v }}" @selected(request('status') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1 mb-3">
                                        <select name="juz" class="form-control form-control-sm">
                                            <option value="">Juz</option>
                                            @for ($j = 1; $j <= 30; $j++)
                                                <option value="{{ $j }}" @selected((string) request('juz') === (string) $j)>
                                                    {{ $j }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                @endif
                                @if (in_array($tab, ['progress', 'hafalan'], true))
                                    <div class="col-md-3 mb-3">
                                        <select name="status" class="form-control form-control-sm">
                                            <option value="">Semua</option>
                                            <option value="idle" @selected(request('status') === 'idle')>Tidak
                                                {{ $tab === 'progress' ? 'ngaji' : 'setor' }} &gt; 7 hari</option>
                                        </select>
                                    </div>
                                @endif
                            </div>

                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.boarding.mutabaah.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    {{-- ================= TABEL ================= --}}
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">

                                {{-- ---------- MUTABA'AH ---------- --}}
                                @if ($tab === 'mutabaah')
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Santri</th>
                                            <th>Bacaan</th>
                                            <th class="text-center">Nilai</th>
                                            <th>Ustadz</th>
                                            <th>Paraf</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $m)
                                            <tr>
                                                <td class="text-nowrap">{{ $fmtDate($m->tanggal) }}<div
                                                        class="text-small text-muted">{{ $sesi[$m->sesi] ?? $m->sesi }}
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($m->santri)
                                                        <a
                                                            href="{{ route('superadmin.boarding.mutabaah.santri', $m->santri->id) }}">{{ $m->santri->name }}</a>
                                                    @else
                                                        -
                                                    @endif
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$m->company_id] ?? '' }}</div>
                                                </td>
                                                <td>{{ $m->label_posisi }}@if ($m->catatan)
                                                        <div class="text-small text-muted">
                                                            {{ \Illuminate\Support\Str::limit($m->catatan, 50) }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span
                                                        class="badge badge-{{ $nilaiColor($m->keterangan) }}">{{ $m->keterangan }}</span>
                                                    <div
                                                        class="text-small {{ $m->is_lanjut ? 'text-success' : 'text-warning' }}">
                                                        {{ $m->is_lanjut ? 'lanjut' : 'ulang' }}</div>
                                                </td>
                                                <td class="text-small">{{ $m->ustadz->name ?? '-' }}</td>
                                                <td class="text-small">
                                                    @if ($m->signed_by)
                                                        <i class="fas fa-signature text-success"></i>
                                                        {{ $m->penandatangan->name ?? '' }}
                                                    @else
                                                        <span class="text-warning">belum</span>
                                                    @endif
                                                </td>
                                                <td class="text-right">{!! $delBtn(
                                                    'mutabaah',
                                                    $m->id,
                                                    "catatan mutaba'ah " . ($m->santri->name ?? '') . ' ' . optional($m->tanggal)->format('d M'),
                                                ) !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Tidak ada catatan
                                                    mutaba'ah pada periode ini.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- POSISI BACAAN ---------- --}}
                                @elseif ($tab === 'progress')
                                    <thead>
                                        <tr>
                                            <th>Santri</th>
                                            <th>Posisi Terakhir</th>
                                            <th>Halaman Berikutnya</th>
                                            <th>Terakhir Ngaji</th>
                                            <th class="text-center">Sesi 30 Hari</th>
                                            <th class="text-center">% Lanjut</th>
                                            <th class="text-center">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $m)
                                            @php
                                                $days = $m->tanggal ? $m->tanggal->diffInDays(today()) : null;
                                                $rate = $m->sessions_30
                                                    ? round(($m->lanjut_30 / $m->sessions_30) * 100)
                                                    : null;
                                            @endphp
                                            <tr>
                                                <td>
                                                    @if ($m->santri)
                                                        <a href="{{ route('superadmin.boarding.mutabaah.santri', $m->santri->id) }}"
                                                            class="font-weight-bold">{{ $m->santri->name }}</a>
                                                    @endif
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$m->company_id] ?? '' }}</div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $m->kitab === 'quran' ? 'success' : 'info' }}">{{ $kitab[$m->kitab] ?? $m->kitab }}</span>
                                                    Jilid {{ $m->jilid }} · {{ $m->label_halaman }}
                                                </td>
                                                <td>Hal. {{ $Model::halamanBerikutnya($m) }}</td>
                                                <td
                                                    class="text-nowrap {{ $days !== null && $days > 7 ? 'text-danger' : '' }}">
                                                    {{ $fmtDate($m->tanggal) }}
                                                    @if ($days !== null)
                                                        <div class="text-small">
                                                            {{ $days === 0 ? 'hari ini' : $days . ' hari lalu' }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ (int) $m->sessions_30 ?: '-' }}</td>
                                                <td class="text-center">{{ $rate !== null ? $rate . '%' : '-' }}</td>
                                                <td class="text-center">{{ (int) $m->total_all }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Belum ada catatan
                                                    mutaba'ah.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- SETORAN TAHFIDZ ---------- --}}
                                @elseif ($tab === 'tahfidz')
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Murid</th>
                                            <th>Hafalan</th>
                                            <th>Jenis</th>
                                            <th>Status</th>
                                            <th>Guru</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $t)
                                            <tr>
                                                <td class="text-nowrap">{{ $fmtDate($t->tanggal) }}</td>
                                                <td>
                                                    @if ($t->student)
                                                        @if ($studentUrl($t->student->id))
                                                            <a
                                                                href="{{ $studentUrl($t->student->id) }}">{{ $t->student->name }}</a>
                                                        @else
                                                            {{ $t->student->name }}
                                                        @endif
                                                        <div class="text-small text-muted">
                                                            {{ $t->student->classRoom->name ?? '' }} ·
                                                            {{ $companyNames[$t->company_id] ?? '' }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <b>{{ $t->surah }}</b> {{ $t->ayat_awal }}–{{ $t->ayat_akhir }}
                                                    <div class="text-small text-muted">Juz {{ $t->juz }} ·
                                                        {{ $t->ayat_akhir - $t->ayat_awal + 1 }} ayat</div>
                                                    @if ($t->ayat_akhir < $t->ayat_awal)
                                                        <span class="badge badge-danger">ayat terbalik</span>
                                                    @endif
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $t->jenis === 'ziyadah' ? 'primary' : 'light' }}">{{ $t->jenis === 'ziyadah' ? 'Ziyadah' : "Muraja'ah" }}</span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $tahfidzBadge[$t->status] ?? 'light' }}">{{ $tahfidzStatus[$t->status] ?? $t->status }}</span>
                                                    @if ($t->catatan)
                                                        <div class="text-small text-muted">
                                                            {{ \Illuminate\Support\Str::limit($t->catatan, 40) }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $t->guru->name ?? '-' }}</td>
                                                <td class="text-right">{!! $delBtn('tahfidz', $t->id, 'setoran ' . $t->surah . ' ' . ($t->student->name ?? '')) !!}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Tidak ada setoran
                                                    tahfidz pada periode ini.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- REKAP HAFALAN ---------- --}}
                                @else
                                    <thead>
                                        <tr>
                                            <th>Murid</th>
                                            <th class="text-center">Juz Tertinggi</th>
                                            <th class="text-center">Juz Disetor</th>
                                            <th class="text-center">Ziyadah</th>
                                            <th class="text-center">Muraja'ah</th>
                                            <th class="text-center">30 Hari</th>
                                            <th class="text-center">% Lancar</th>
                                            <th>Setoran Terakhir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $r)
                                            @php
                                                $st = $students[$r->student_id] ?? null;
                                                $days = $r->last_date
                                                    ? \Carbon\Carbon::parse($r->last_date)->diffInDays(today())
                                                    : null;
                                            @endphp
                                            <tr>
                                                <td>
                                                    @if ($st)
                                                        @if ($studentUrl($st->id))
                                                            <a href="{{ $studentUrl($st->id) }}"
                                                                class="font-weight-bold">{{ $st->name }}</a>
                                                        @else
                                                            {{ $st->name }}
                                                        @endif
                                                        <div class="text-small text-muted">
                                                            {{ $st->classRoom->name ?? '' }} ·
                                                            {{ $companyNames[$r->company_id] ?? '' }}</div>
                                                    @else
                                                        Murid #{{ $r->student_id }}
                                                    @endif
                                                </td>
                                                <td class="text-center font-weight-bold">{{ $r->max_juz }}</td>
                                                <td class="text-center">{{ $r->juz_count }}</td>
                                                <td class="text-center">{{ (int) $r->ziyadah }}</td>
                                                <td class="text-center">{{ (int) $r->murajaah }}</td>
                                                <td class="text-center">{{ (int) $r->last30 ?: '-' }}</td>
                                                <td class="text-center">
                                                    {{ $r->total ? round(($r->lancar / $r->total) * 100) . '%' : '-' }}</td>
                                                <td
                                                    class="text-nowrap {{ $days !== null && $days > 7 ? 'text-danger' : '' }}">
                                                    {{ $fmtDate($r->last_date) }}
                                                    @if ($days !== null)
                                                        <div class="text-small">
                                                            {{ $days === 0 ? 'hari ini' : $days . ' hari lalu' }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Belum ada setoran
                                                    tahfidz.</td>
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
