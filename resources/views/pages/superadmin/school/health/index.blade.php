@extends('layouts.app')

@section('title', 'Kesehatan / UKS')

@section('main')
    @php
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $tabUrl = fn($key, $extra = []) => route(
            'superadmin.school.health.index',
            array_filter(
                [
                    'tab' => $key,
                    'company_id' => request('company_id'),
                    'date_from' => request('date_from'),
                    'date_to' => request('date_to'),
                ] + $extra,
                fn($v) => $v !== null && $v !== '',
            ),
        );
        $hasFilter = collect(request()->except(['tab', 'page']))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();
        $rangeLabel = $from->translatedFormat('d M Y') . ' – ' . $to->translatedFormat('d M Y');
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Kesehatan / UKS</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring Sekolah</div>
                    <div class="breadcrumb-item">Kesehatan / UKS</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="alert alert-light border">
                    <i class="fas fa-user-shield text-primary"></i>
                    <b>Data kesehatan murid termasuk data pribadi sensitif.</b> Menu ini hanya untuk melihat.
                    Detail kesehatan dibuka per murid dan setiap pembukaan tercatat di Audit Log. Export hanya berisi rekap
                    angka per sekolah.
                </div>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ $tabUrl($key) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>

                @if ($tab === 'visits')
                    <div class="row">
                        @php $totalVisits = $byOutcome->sum(); @endphp
                        <div class="col-6 col-lg-3">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-primary"><i class="fas fa-clinic-medical"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Kunjungan</h4>
                                    </div>
                                    <div class="card-body" style="font-size:18px">{{ number_format($totalVisits) }}</div>
                                </div>
                            </div>
                        </div>
                        @foreach ($outcomes as $v => $l)
                            <div class="col-6 col-lg-3">
                                <a href="{{ $tabUrl('visits', ['outcome' => $v]) }}" class="text-decoration-none">
                                    <div class="card card-statistic-1">
                                        <div class="card-icon bg-{{ $outcomeBadge[$v] }}"><i
                                                class="fas fa-{{ ['kembali_kelas' => 'door-open', 'pulang' => 'home', 'dirujuk' => 'ambulance'][$v] }}"></i>
                                        </div>
                                        <div class="card-wrap">
                                            <div class="card-header">
                                                <h4>{{ $l }}</h4>
                                            </div>
                                            <div class="card-body" style="font-size:18px">
                                                {{ number_format((int) ($byOutcome[$v] ?? 0)) }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                    @if ($frequent->isNotEmpty())
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Murid yang ke UKS 3 kali atau lebih pada periode
                            ini:
                            @foreach ($frequent as $f)
                                <a href="{{ route('superadmin.school.health.show', $f->student_id) }}"
                                    class="alert-link">{{ $frequentNames[$f->student_id] ?? '#' . $f->student_id }}</a>
                                ({{ $f->total }}x)
                                @if (!$loop->last)
                                    ,
                                @endif
                            @endforeach
                        </div>
                    @endif
                @elseif ($tab === 'records')
                    <div class="row">
                        @foreach ([['Data Kesehatan', $summary['total'], 'primary', 'fa-notes-medical', null], ['Punya Alergi', $summary['allergy'], 'warning', 'fa-allergies', 'allergy'], ['Penyakit Kronis', $summary['chronic'], 'danger', 'fa-heartbeat', 'chronic'], ['Tanpa Kontak Darurat', $summary['no_emergency'], $summary['no_emergency'] ? 'secondary' : 'success', 'fa-phone-slash', 'no_emergency']] as [$label, $value, $color, $icon, $flag])
                            <div class="col-6 col-lg-3">
                                @if ($flag)
                                    <a href="{{ $tabUrl('records', ['flag' => $flag]) }}" class="text-decoration-none">
                                @endif
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i>
                                    </div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>{{ $label }}</h4>
                                        </div>
                                        <div class="card-body" style="font-size:18px">{{ number_format($value) }}</div>
                                    </div>
                                </div>
                                @if ($flag)
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.school.health.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="this.form.class_id && (this.form.class_id.value=''); this.form.submit()">
                                        <option value="">Semua Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($tab !== 'records')
                                    <div class="col-md-2 mb-3"><input type="date" name="date_from"
                                            value="{{ $from->toDateString() }}" class="form-control" title="Dari tanggal">
                                    </div>
                                    <div class="col-md-2 mb-3"><input type="date" name="date_to"
                                            value="{{ $to->toDateString() }}" class="form-control" title="Sampai tanggal">
                                    </div>
                                @endif
                                @if ($tab !== 'recap')
                                    <div class="col-md-2 mb-3">
                                        <select name="class_id" class="form-control" @disabled($classOptions->isEmpty())>
                                            <option value="">
                                                {{ $classOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Kelas' }}
                                            </option>
                                            @foreach ($classOptions as $c)
                                                <option value="{{ $c->id }}" @selected((string) request('class_id') === (string) $c->id)>
                                                    {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if ($tab === 'visits')
                                        <div class="col-md-2 mb-3">
                                            <select name="outcome" class="form-control">
                                                <option value="">Semua Hasil</option>
                                                @foreach ($outcomes as $v => $l)
                                                    <option value="{{ $v }}" @selected(request('outcome') === $v)>
                                                        {{ $l }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @else
                                        <div class="col-md-2 mb-3">
                                            <select name="flag" class="form-control">
                                                <option value="">Semua</option>
                                                <option value="allergy" @selected(request('flag') === 'allergy')>Punya alergi</option>
                                                <option value="chronic" @selected(request('flag') === 'chronic')>Penyakit kronis
                                                </option>
                                                <option value="no_emergency" @selected(request('flag') === 'no_emergency')>Tanpa kontak
                                                    darurat</option>
                                            </select>
                                        </div>
                                    @endif
                                    <div class="col-md mb-3"><input type="text" name="search"
                                            value="{{ request('search') }}" class="form-control"
                                            placeholder="Nama / NIS murid"></div>
                                @endif
                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    @if ($tab === 'recap')
                                        <a href="{{ route('superadmin.school.health.export', request()->query() + ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}"
                                            class="btn btn-outline-success" title="Unduh rekap (tanpa data pribadi)"><i
                                                class="fas fa-file-excel"></i></a>
                                    @endif
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.school.health.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                @if ($tab === 'recap')
                                    <thead>
                                        <tr>
                                            <th>Sekolah</th>
                                            <th style="width:180px">Data Kesehatan Murid</th>
                                            <th class="text-center">Alergi</th>
                                            <th class="text-center">Kronis</th>
                                            <th class="text-center">Kunjungan UKS</th>
                                            <th class="text-center">Dipulangkan</th>
                                            <th class="text-center">Dirujuk</th>
                                            <th>Kunjungan Terakhir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recap as $r)
                                            <tr>
                                                <td><a href="{{ $tabUrl('visits', ['company_id' => $r['id']]) }}"
                                                        class="font-weight-bold">{{ $r['name'] }}</a>
                                                    <div class="text-small text-muted">{{ $r['students'] }} murid aktif
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($r['coverage'] !== null)
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-fill mr-2" style="height:8px">
                                                                <div class="progress-bar bg-{{ $r['coverage'] >= 80 ? 'success' : ($r['coverage'] >= 40 ? 'warning' : 'danger') }}"
                                                                    style="width:{{ min(100, $r['coverage']) }}%"></div>
                                                            </div>
                                                            <span
                                                                class="text-small">{{ $r['records'] }}/{{ $r['students'] }}</span>
                                                        </div>
                                                    @else
                                                        <span class="text-muted text-small">Belum ada murid</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $r['allergy'] ?: '-' }}</td>
                                                <td class="text-center">{{ $r['chronic'] ?: '-' }}</td>
                                                <td class="text-center">{{ $r['visits'] ?: '-' }}@if ($r['visitors'])
                                                        <div class="text-small text-muted">{{ $r['visitors'] }} murid
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $r['pulang'] ?: '-' }}</td>
                                                <td
                                                    class="text-center {{ $r['dirujuk'] ? 'text-danger font-weight-bold' : '' }}">
                                                    {{ $r['dirujuk'] ?: '-' }}</td>
                                                <td class="text-small">
                                                    {{ $r['last_visit'] ? \Carbon\Carbon::parse($r['last_visit'])->format('d M Y H:i') : '-' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Tidak ada sekolah.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                @elseif ($tab === 'visits')
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Murid</th>
                                            <th>Keluhan</th>
                                            <th>Hasil</th>
                                            <th>Dicatat</th>
                                            <th class="text-right">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $v)
                                            <tr>
                                                <td class="text-nowrap">
                                                    {{ optional($v->visited_at)->format('d M Y H:i') }}</td>
                                                <td>
                                                    {{ $v->student->name ?? '-' }}
                                                    <div class="text-small text-muted">
                                                        {{ $v->student->classRoom->name ?? '' }} ·
                                                        {{ $companyNames[$v->company_id] ?? '' }}</div>
                                                </td>
                                                <td class="text-small">
                                                    {{ \Illuminate\Support\Str::limit($v->complaint, 60) }}</td>
                                                <td><span
                                                        class="badge badge-{{ $outcomeBadge[$v->outcome] ?? 'light' }}">{{ $outcomes[$v->outcome] ?? $v->outcome }}</span>
                                                </td>
                                                <td class="text-small">{{ $v->recordedBy->name ?? '-' }}</td>
                                                <td class="text-right">
                                                    @if ($v->student)
                                                        <a href="{{ route('superadmin.school.health.show', $v->student->id) }}"
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="Buka data kesehatan murid (tercatat di Audit Log)"><i
                                                                class="fas fa-eye"></i></a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Tidak ada kunjungan
                                                    UKS pada periode ini.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                @else
                                    <thead>
                                        <tr>
                                            <th>Murid</th>
                                            <th>Gol. Darah</th>
                                            <th>Catatan Penting</th>
                                            <th>Kontak Darurat</th>
                                            <th>Diperbarui</th>
                                            <th class="text-right">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $h)
                                            <tr>
                                                <td>
                                                    {{ $h->student->name ?? '-' }}
                                                    <div class="text-small text-muted">
                                                        {{ $h->student->classRoom->name ?? '' }} ·
                                                        {{ $companyNames[$h->company_id] ?? '' }}</div>
                                                </td>
                                                <td>{{ $h->blood_type ?: '-' }}</td>
                                                <td>
                                                    @if ($h->allergies)
                                                        <span class="badge badge-warning">alergi</span>
                                                    @endif
                                                    @if ($h->chronic_conditions)
                                                        <span class="badge badge-danger">penyakit kronis</span>
                                                    @endif
                                                    @if (!$h->allergies && !$h->chronic_conditions)
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($h->emergency_contact_phone)
                                                        <i class="fas fa-check text-success"></i> <span
                                                            class="text-small">{{ $h->emergency_contact_name }}</span>
                                                    @else
                                                        <span class="text-warning text-small"><i
                                                                class="fas fa-phone-slash"></i> belum diisi</span>
                                                    @endif
                                                </td>
                                                <td class="text-small">{{ $fmtDate($h->recorded_at) }}</td>
                                                <td class="text-right">
                                                    @if ($h->student)
                                                        <a href="{{ route('superadmin.school.health.show', $h->student->id) }}"
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="Buka data kesehatan murid (tercatat di Audit Log)"><i
                                                                class="fas fa-eye"></i></a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Belum ada data
                                                    kesehatan.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                @endif
                            </table>
                        </div>
                    </div>

                    @if ($tab !== 'recap' && $items->hasPages())
                        <div class="card-footer text-right">{{ $items->links() }}</div>
                    @endif
                </div>

                @if ($tab === 'recap')
                    <p class="text-small text-muted">Periode kunjungan: {{ $rangeLabel }}. Data kesehatan dihitung dari
                        murid aktif.</p>
                @endif
            </div>
        </section>
    </div>
@endsection
