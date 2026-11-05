@extends('layouts.app')

@section('title', 'Hari Libur')

@section('main')
    @php
        $whenLabel = ['upcoming' => 'Akan datang', 'ongoing' => 'Sedang berlangsung', 'past' => 'Sudah lewat'];
        $whenBadge = ['upcoming' => 'info', 'ongoing' => 'success', 'past' => 'secondary'];
        $hasFilter = collect(request()->only(['company_id', 'type', 'when', 'search']))->filter()->isNotEmpty();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Hari Libur</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.monitoring.holidays.create') }}" class="btn btn-primary"><i
                            class="fas fa-plus"></i> Tambah Libur</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring</div>
                    <div class="breadcrumb-item">Hari Libur</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Hari libur memengaruhi absen dan pengingat karyawan di tiap perusahaan.
                    Libur nasional bisa ditambahkan ke banyak perusahaan sekaligus.
                </p>

                @if ($noNational->isNotEmpty())
                    <div class="alert alert-light border">
                        <i class="fas fa-info-circle text-primary"></i>
                        {{ $noNational->count() }} perusahaan belum punya libur nasional di tahun {{ $year }}:
                        {{ $noNational->take(5)->pluck('name')->implode(', ') }}{{ $noNational->count() > 5 ? ', …' : '' }}.
                        <a
                            href="{{ route('superadmin.monitoring.holidays.create', ['company_ids' => $noNational->pluck('id')->all()]) }}">Tambahkan
                            libur nasional</a>
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-4">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-danger"><i class="fas fa-flag"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Libur Nasional {{ $year }}</h4>
                                </div>
                                <div class="card-body">{{ (int) ($summary->national ?? 0) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-primary"><i class="fas fa-building"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Libur Perusahaan {{ $year }}</h4>
                                </div>
                                <div class="card-body">{{ (int) ($summary->company ?? 0) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-info"><i class="fas fa-users"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Perusahaan</h4>
                                </div>
                                <div class="card-body">{{ (int) ($summary->companies ?? 0) }} <small class="text-muted"
                                        style="font-size:12px">dari {{ $companies->count() }}</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.monitoring.holidays.index') }}">
                            <div class="form-row">
                                <div class="col-md-2 mb-3">
                                    <select name="year" class="form-control">
                                        @foreach ($years as $y)
                                            <option value="{{ $y }}" @selected($year === $y)>
                                                {{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control">
                                        <option value="">Semua Perusahaan</option>
                                        @foreach ($companies as $c)
                                            <option value="{{ $c->id }}" @selected((string) request('company_id') === (string) $c->id)>
                                                {{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="type" class="form-control">
                                        <option value="">Semua Jenis</option>
                                        @foreach ($typeLabels as $value => $label)
                                            <option value="{{ $value }}" @selected(request('type') === $value)>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="when" class="form-control">
                                        <option value="">Semua Waktu</option>
                                        @foreach ($whenLabel as $value => $label)
                                            <option value="{{ $value }}" @selected(request('when') === $value)>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control" placeholder="Nama libur">
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.monitoring.holidays.index', ['year' => $year]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama Libur</th>
                                        <th>Perusahaan</th>
                                        <th>Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($holidays as $h)
                                        <tr>
                                            <td class="text-small" style="min-width:170px">{{ $h->period_label }}</td>
                                            <td>
                                                <strong>{{ $h->name }}</strong>
                                                <span
                                                    class="badge badge-{{ $h->type === 'national' ? 'danger' : 'primary' }}">{{ $typeLabels[$h->type] ?? $h->type }}</span>
                                                @if ($h->note)
                                                    <div class="text-small text-muted">
                                                        {{ \Illuminate\Support\Str::limit($h->note, 70) }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $companyNames[$h->company_id] ?? '-' }}</td>
                                            <td>
                                                <span
                                                    class="badge badge-{{ $whenBadge[$h->time_status] ?? 'light' }}">{{ $whenLabel[$h->time_status] ?? $h->time_status }}</span>
                                                @if ($h->reminder_sent_at)
                                                    <div class="text-small text-muted"><i class="fas fa-bell"></i> pengingat
                                                        terkirim</div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.monitoring.holidays.edit', $h->id) }}"
                                                    class="btn btn-sm btn-primary" title="Edit"><i
                                                        class="fas fa-edit"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Belum ada hari libur di
                                                tahun {{ $year }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($holidays->hasPages())
                        <div class="card-footer text-right">{{ $holidays->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
