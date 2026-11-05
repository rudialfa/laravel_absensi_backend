@extends('layouts.app')

@section('title', 'Laporan & Kinerja')

@section('main')
    @php
        $period = sprintf('%04d-%02d', $year, $month);
        $periodLabel = \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y');
        $keep = array_filter(['company_id' => request('company_id'), 'period' => request('period')]);
        $hasFilter = collect(request()->except(['tab', 'page', 'period']))->filter()->isNotEmpty();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Laporan &amp; Kinerja</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring</div>
                    <div class="breadcrumb-item">Laporan &amp; Kinerja</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Laporan harian dan bulanan karyawan, skor kinerja, dan catatan HR dari semua perusahaan.
                    Penilaian tetap dilakukan HR lewat aplikasi.
                </p>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ route('superadmin.monitoring.reports.index', $keep + ['tab' => $key]) }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>

                {{-- Filter --}}
                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.monitoring.reports.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control">
                                        <option value="">Semua Perusahaan</option>
                                        @foreach ($companies as $c)
                                            <option value="{{ $c->id }}" @selected((string) request('company_id') === (string) $c->id)>
                                                {{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($tab !== 'notes')
                                    <div class="col-md-2 mb-3">
                                        <select name="period" class="form-control">
                                            @foreach ($monthOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($period === $value)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($tab === 'daily')
                                    <div class="col-md-2 mb-3">
                                        <select name="result" class="form-control">
                                            <option value="">Semua Hasil</option>
                                            <option value="achieved" @selected(request('result') === 'achieved')>Tercapai</option>
                                            <option value="not_achieved" @selected(request('result') === 'not_achieved')>Tidak tercapai
                                            </option>
                                            <option value="empty" @selected(request('result') === 'empty')>Pencapaian belum diisi
                                            </option>
                                        </select>
                                    </div>
                                @elseif ($tab === 'monthly')
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua Status</option>
                                            @foreach ($statusLabels as $value => $label)
                                                <option value="{{ $value }}" @selected(request('status') === $value)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif ($tab === 'notes')
                                    <div class="col-md-2 mb-3">
                                        <select name="type" class="form-control">
                                            <option value="">Semua Jenis</option>
                                            @foreach ($noteTypes as $value => $label)
                                                <option value="{{ $value }}" @selected(request('type') === $value)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="read" class="form-control">
                                            <option value="">Dibaca / belum</option>
                                            <option value="1" @selected(request('read') === '1')>Sudah dibaca</option>
                                            <option value="0" @selected(request('read') === '0')>Belum dibaca</option>
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md-3 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control"
                                        placeholder="Nama karyawan{{ $tab === 'daily' ? ' atau target' : ($tab === 'notes' ? ' atau judul' : '') }}">
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block"><i class="fas fa-search"></i></button>
                                </div>
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('superadmin.monitoring.reports.export', request()->query() + ['tab' => $tab]) }}"
                                        class="btn btn-outline-success btn-block" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.monitoring.reports.index', ['tab' => $tab, 'period' => request('period')]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>
                </div>

                {{-- ═════════ LAPORAN HARIAN ═════════ --}}
                @if ($tab === 'daily')
                    @if ($compliance->isNotEmpty())
                        <div class="card">
                            <div class="card-header">
                                <h4>Kepatuhan Laporan Harian · {{ $periodLabel }}</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="pl-4">Perusahaan</th>
                                            <th class="text-center">Karyawan</th>
                                            <th class="text-center">Yang melapor</th>
                                            <th class="text-center">Total laporan</th>
                                            <th style="width:180px">Target tercapai</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($compliance as $row)
                                            <tr>
                                                <td class="pl-4"><a
                                                        href="{{ route('superadmin.monitoring.reports.index', ['tab' => 'daily', 'company_id' => $row->company_id, 'period' => $period]) }}">{{ $companyNames[$row->company_id] ?? '-' }}</a>
                                                </td>
                                                <td class="text-center">{{ $row->employees }}</td>
                                                <td class="text-center">{{ $row->reporters }}</td>
                                                <td class="text-center">{{ $row->reports }}</td>
                                                <td>
                                                    @if ($row->rate !== null)
                                                        <div class="progress" style="height:6px">
                                                            <div class="progress-bar bg-{{ $row->rate >= 75 ? 'success' : ($row->rate >= 50 ? 'warning' : 'danger') }}"
                                                                style="width: {{ $row->rate }}%"></div>
                                                        </div>
                                                        <small class="text-muted">{{ $row->rate }}%
                                                            ({{ $row->achieved }})</small>
                                                    @else
                                                        <small class="text-muted">belum ada pencapaian</small>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Karyawan</th>
                                            <th>Target</th>
                                            <th>Pencapaian</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $d)
                                            <tr>
                                                <td class="text-small">{{ optional($d->date)->translatedFormat('D, d M') }}
                                                </td>
                                                <td>
                                                    <strong>{{ $d->user->name ?? '-' }}</strong>
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$d->company_id] ?? '-' }}</div>
                                                </td>
                                                <td class="text-small" style="max-width:260px">
                                                    {{ \Illuminate\Support\Str::limit($d->target, 90) }}</td>
                                                <td class="text-small" style="max-width:260px">
                                                    @if ($d->achievement === null)
                                                        <span class="badge badge-light">belum diisi</span>
                                                    @else
                                                        <span
                                                            class="badge badge-{{ $d->is_achieved ? 'success' : 'danger' }}">{{ $d->is_achieved ? 'Tercapai' : 'Tidak tercapai' }}</span>
                                                        <div>{{ \Illuminate\Support\Str::limit($d->achievement, 80) }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-center"><a
                                                        href="{{ route('superadmin.monitoring.reports.show', ['daily', $d->id]) }}"
                                                        class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">Belum ada laporan
                                                    harian di {{ $periodLabel }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if ($items->hasPages())
                            <div class="card-footer text-right">{{ $items->links() }}</div>
                        @endif
                    </div>

                    {{-- ═════════ LAPORAN BULANAN ═════════ --}}
                @elseif ($tab === 'monthly')
                    <div class="mb-3">
                        @foreach ($statusLabels as $key => $label)
                            <span class="badge badge-{{ $badge[$key] ?? 'light' }} mr-1">{{ $label }}:
                                {{ $statusCounts[$key] ?? 0 }}</span>
                        @endforeach
                    </div>
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Karyawan</th>
                                            <th>Periode</th>
                                            <th>Target</th>
                                            <th>Status</th>
                                            <th class="text-center">Skor</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $r)
                                            <tr>
                                                <td>
                                                    <strong>{{ $r->user->name ?? '-' }}</strong>
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$r->company_id] ?? '-' }}</div>
                                                </td>
                                                <td>{{ $r->period_label }}</td>
                                                <td class="text-small" style="max-width:300px">
                                                    {{ \Illuminate\Support\Str::limit($r->target, 90) ?: '-' }}</td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $badge[$r->status] ?? 'light' }}">{{ $statusLabels[$r->status] ?? $r->status }}</span>
                                                    @if ($r->approver)
                                                        <div class="text-small text-muted">oleh {{ $r->approver->name }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    {{ $r->score ? rtrim(rtrim(number_format($r->score, 2), '0'), '.') : '-' }}
                                                </td>
                                                <td class="text-center"><a
                                                        href="{{ route('superadmin.monitoring.reports.show', ['monthly', $r->id]) }}"
                                                        class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Belum ada laporan
                                                    bulanan untuk {{ $periodLabel }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if ($items->hasPages())
                            <div class="card-footer text-right">{{ $items->links() }}</div>
                        @endif
                    </div>

                    {{-- ═════════ SKOR KINERJA ═════════ --}}
                @elseif ($tab === 'scores')
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-primary"><i class="fas fa-users"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Karyawan Dinilai</h4>
                                    </div>
                                    <div class="card-body">{{ (int) ($average->total ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-success"><i class="fas fa-star"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Rata-rata Skor</h4>
                                    </div>
                                    <div class="card-body">{{ number_format((float) ($average->score ?? 0), 1) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-info"><i class="fas fa-bullseye"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>Rata-rata Target Tercapai</h4>
                                    </div>
                                    <div class="card-body">{{ number_format((float) ($average->rate ?? 0), 1) }}%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h4>Peringkat · {{ $periodLabel }}</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Karyawan</th>
                                            <th class="text-center">Target</th>
                                            <th style="width:200px">Tercapai</th>
                                            <th class="text-center">Skor Akhir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $i => $s)
                                            <tr>
                                                <td>{{ $items->firstItem() + $i }}</td>
                                                <td>
                                                    <strong>{{ $s->user->name ?? '-' }}</strong>
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$s->company_id] ?? '-' }}{{ $s->user && $s->user->department ? ' · ' . $s->user->department : '' }}
                                                    </div>
                                                </td>
                                                <td class="text-center">{{ $s->targets_achieved }} /
                                                    {{ $s->total_targets }}</td>
                                                <td>
                                                    <div class="progress" style="height:6px">
                                                        <div class="progress-bar"
                                                            style="width: {{ min(100, (float) $s->achievement_rate) }}%">
                                                        </div>
                                                    </div>
                                                    <small
                                                        class="text-muted">{{ number_format((float) $s->achievement_rate, 1) }}%</small>
                                                </td>
                                                <td class="text-center">
                                                    <strong>{{ number_format((float) $s->final_score, 1) }}</strong></td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">Belum ada skor
                                                    kinerja untuk {{ $periodLabel }}. Skor dibuat HR dari aplikasi.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if ($items->hasPages())
                            <div class="card-footer text-right">{{ $items->links() }}</div>
                        @endif
                    </div>

                    {{-- ═════════ CATATAN HR ═════════ --}}
                @else
                    <div class="mb-3">
                        @foreach ($noteTypes as $key => $label)
                            <span class="badge badge-{{ $badge[$key] ?? 'light' }} mr-1">{{ $label }}:
                                {{ $typeCounts[$key] ?? 0 }}</span>
                        @endforeach
                    </div>
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Untuk</th>
                                            <th>Catatan</th>
                                            <th>Dari</th>
                                            <th>Dibaca</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $n)
                                            <tr>
                                                <td>
                                                    <strong>{{ $n->user->name ?? '-' }}</strong>
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$n->company_id] ?? '-' }}</div>
                                                </td>
                                                <td style="max-width:320px">
                                                    <span
                                                        class="badge badge-{{ $badge[$n->type] ?? 'light' }}">{{ $noteTypes[$n->type] ?? $n->type }}</span>
                                                    <strong>{{ $n->title }}</strong>
                                                    <div class="text-small text-muted">
                                                        {{ \Illuminate\Support\Str::limit($n->note, 90) }}</div>
                                                </td>
                                                <td class="text-small">
                                                    {{ $n->creator->name ?? '-' }}<br>{{ optional($n->created_at)->format('d M Y') }}
                                                </td>
                                                <td>
                                                    @if ($n->is_read)
                                                        <span class="text-success text-small"><i
                                                                class="fas fa-check-double"></i>
                                                            {{ optional($n->read_at)->format('d M H:i') }}</span>
                                                    @else
                                                        <span class="text-muted text-small">Belum</span>
                                                    @endif
                                                </td>
                                                <td class="text-center"><a
                                                        href="{{ route('superadmin.monitoring.reports.show', ['notes', $n->id]) }}"
                                                        class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">Belum ada catatan
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if ($items->hasPages())
                            <div class="card-footer text-right">{{ $items->links() }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
