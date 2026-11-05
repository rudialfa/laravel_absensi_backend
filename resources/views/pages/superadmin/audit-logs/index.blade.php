@extends('layouts.app')

@section('title', 'Audit Log')

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        $hasFilter = collect(request()->only(['search', 'kind', 'action', 'user_id', 'company_id', 'subject_type', 'subject_id', 'ip', 'from', 'to']))->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Audit Log</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Audit Log</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">Jejak semua aksi penting di panel superadmin. Hanya bisa dilihat dan di-export — tidak bisa diubah atau dihapus.</p>

                <div class="row">
                    @foreach ([
                        ['Hari Ini', $stats['today'], 'primary', 'fa-calendar-day'],
                        ['7 Hari', $stats['week'], 'info', 'fa-calendar-week'],
                        ['Penghapusan 7 Hari', $stats['deletes'], $stats['deletes'] ? 'danger' : 'secondary', 'fa-trash'],
                        ['Total Log', $stats['total'], 'secondary', 'fa-history'],
                    ] as [$label, $value, $color, $icon])
                        <div class="col-6 col-lg-3">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></div>
                                <div class="card-wrap">
                                    <div class="card-header"><h4>{{ $label }}</h4></div>
                                    <div class="card-body">{{ number_format($value) }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="row">
                    <div class="col-12 col-lg-9">
                        <div class="card">
                            <div class="card-header">
                                <h4>Log Aktivitas</h4>
                                <div class="card-header-action">
                                    <a href="{{ route('superadmin.audit-logs.export', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> Export CSV</a>
                                </div>
                            </div>
                            <div class="card-body pb-0">
                                <form method="GET" action="{{ route('superadmin.audit-logs') }}">
                                    <div class="form-row">
                                        <div class="col-md-4 mb-2">
                                            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari deskripsi / aksi…">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <select name="kind" class="form-control">
                                                <option value="">Semua jenis</option>
                                                @foreach ($kinds as $k => [$label, $color])
                                                    <option value="{{ $k }}" @selected(request('kind') === $k)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <select name="user_id" class="form-control">
                                                <option value="">Semua pelaku</option>
                                                <option value="system" @selected(request('user_id') === 'system')>Sistem (tanpa login)</option>
                                                @foreach ($actors as $a)
                                                    <option value="{{ $a->id }}" @selected((string) request('user_id') === (string) $a->id)>{{ $a->name }}{{ $a->company_id ? ' · tenant' : '' }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <select name="company_id" class="form-control">
                                                <option value="">Semua tenant</option>
                                                @foreach ($tenants as $t)
                                                    <option value="{{ $t->id }}" @selected((string) request('company_id') === (string) $t->id)>{{ $t->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="collapse {{ collect(['action', 'subject_type', 'subject_id', 'ip', 'from', 'to'])->contains(fn($k) => request()->filled($k)) ? 'show' : '' }}" id="moreFilter">
                                        <div class="form-row">
                                            <div class="col-md-3 mb-2">
                                                <select name="action" class="form-control">
                                                    <option value="">Semua aksi</option>
                                                    @foreach ($actions as $a)
                                                        <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <select name="subject_type" class="form-control">
                                                    <option value="">Semua jenis data</option>
                                                    @foreach ($subjectTypes as $st)
                                                        <option value="{{ $st }}" @selected(request('subject_type') === $st)>{{ class_basename($st) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <input type="number" name="subject_id" value="{{ request('subject_id') }}" class="form-control" placeholder="ID data" min="1">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <input type="text" name="ip" value="{{ request('ip') }}" class="form-control" placeholder="Alamat IP (awalan)">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="text-small mb-0">Dari</label>
                                                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="text-small mb-0">Sampai</label>
                                                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="text-small mb-0">Per halaman</label>
                                                <select name="per_page" class="form-control">
                                                    <option value="25">25</option>
                                                    <option value="100" @selected(request('per_page') == 100)>100</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center mb-3">
                                        <button class="btn btn-primary mr-2"><i class="fas fa-search"></i> Cari</button>
                                        <button type="button" class="btn btn-light mr-2" data-toggle="collapse" data-target="#moreFilter"><i class="fas fa-sliders-h"></i> Filter lain</button>
                                        @if ($hasFilter)
                                            <a href="{{ route('superadmin.audit-logs') }}" class="btn btn-link">Reset</a>
                                        @endif
                                        <span class="ml-auto text-small text-muted">{{ number_format($logs->total()) }} log</span>
                                    </div>
                                </form>
                            </div>

                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:120px">Waktu</th>
                                                <th>Pelaku</th>
                                                <th>Aksi</th>
                                                <th>Deskripsi</th>
                                                <th>Tenant</th>
                                                <th class="text-right"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($logs as $log)
                                                @php $cid = $log->companyId(); @endphp
                                                <tr>
                                                    <td class="text-small text-nowrap">
                                                        {{ $log->created_at->timezone($tz)->format('d M Y') }}<br>
                                                        <span class="text-muted">{{ $log->created_at->timezone($tz)->format('H:i:s') }}</span>
                                                    </td>
                                                    <td class="text-small">
                                                        @if ($log->user)
                                                            <a href="{{ route('superadmin.audit-logs', ['user_id' => $log->user_id]) }}">{{ $log->user->name }}</a>
                                                        @elseif ($log->user_id)
                                                            <span class="text-muted">#{{ $log->user_id }} (dihapus)</span>
                                                        @else
                                                            <span class="text-muted">Sistem</span>
                                                        @endif
                                                        @if ($log->ip_address)<div class="text-muted">{{ $log->ip_address }}</div>@endif
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-{{ $log->kindColor() }}">{{ $log->kindLabel() }}</span>
                                                        <div><a href="{{ route('superadmin.audit-logs', ['action' => $log->action]) }}" class="text-small text-muted"><code>{{ $log->action }}</code></a></div>
                                                    </td>
                                                    <td class="text-small" style="max-width:380px">
                                                        {{ \Illuminate\Support\Str::limit($log->description, 160) ?: '-' }}
                                                        @if ($log->subject_type)
                                                            <div class="text-muted">{{ $log->subjectName() }} #{{ $log->subject_id }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="text-small">
                                                        @if ($cid)
                                                            <a href="{{ route('superadmin.audit-logs', ['company_id' => $cid]) }}">{{ $companyNames[$cid] ?? '#' . $cid }}</a>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-right">
                                                        <a href="{{ route('superadmin.audit-logs.show', $log->id) }}" class="btn btn-sm btn-light" title="Detail"><i class="fas fa-eye"></i></a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada log{{ $hasFilter ? ' untuk filter ini' : '' }}.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-end">
                                {{ $logs->links() }}
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-3">
                        <div class="card">
                            <div class="card-header"><h4>Aksi Terbanyak (30 hari)</h4></div>
                            <div class="card-body">
                                @forelse ($topActions as $a)
                                    <div class="d-flex justify-content-between text-small mb-1">
                                        <a href="{{ route('superadmin.audit-logs', ['action' => $a->action]) }}" class="text-truncate mr-2"><code>{{ $a->action }}</code></a>
                                        <span class="text-muted">{{ number_format($a->n) }}</span>
                                    </div>
                                @empty
                                    <div class="text-muted text-small">Belum ada.</div>
                                @endforelse
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header"><h4>Pelaku Teraktif (30 hari)</h4></div>
                            <div class="card-body">
                                @forelse ($topUsers as $u)
                                    <div class="d-flex justify-content-between text-small mb-1">
                                        <a href="{{ route('superadmin.audit-logs', ['user_id' => $u->user_id]) }}" class="text-truncate mr-2">{{ $topUserNames[$u->user_id] ?? '#' . $u->user_id }}</a>
                                        <span class="text-muted">{{ number_format($u->n) }}</span>
                                    </div>
                                @empty
                                    <div class="text-muted text-small">Belum ada.</div>
                                @endforelse
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body text-small text-muted">
                                <i class="fas fa-lock"></i> Log tidak bisa dihapus dari panel. Export CSV juga ikut tercatat sebagai <code>export_audit_logs</code>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection