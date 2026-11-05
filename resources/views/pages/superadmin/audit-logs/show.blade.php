@extends('layouts.app')

@section('title', 'Audit Log #' . $log->id)

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        $subjectUrl = $log->subjectUrl();
        $extra = $log->extraMeta();
        $fmt = fn($v) => \App\Models\AuditLog::present($v);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('superadmin.audit-logs') }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Audit Log #{{ $log->id }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.audit-logs') }}">Audit Log</a></div>
                    <div class="breadcrumb-item">#{{ $log->id }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="d-flex justify-content-between mb-3">
                    @if ($prev)
                        <a href="{{ route('superadmin.audit-logs.show', $prev) }}" class="btn btn-sm btn-light"><i
                                class="fas fa-chevron-left"></i> Log sebelumnya</a>
                    @else
                        <span></span>
                    @endif
                    @if ($next)
                        <a href="{{ route('superadmin.audit-logs.show', $next) }}" class="btn btn-sm btn-light">Log
                            berikutnya <i class="fas fa-chevron-right"></i></a>
                    @endif
                </div>

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4><span
                                        class="badge badge-{{ $log->kindColor() }} mr-2">{{ $log->kindLabel() }}</span><code>{{ $log->action }}</code>
                                </h4>
                            </div>
                            <div class="card-body">
                                <p class="mb-0" style="white-space:pre-line">
                                    {{ $log->description ?: 'Tanpa deskripsi.' }}</p>
                            </div>
                        </div>

                        @if ($log->hasDiff())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Perubahan</h4>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Kolom</th>
                                                    <th>Sebelum</th>
                                                    <th>Sesudah</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($log->diffRows() as [$key, $before, $after, $changed])
                                                    <tr class="{{ $changed ? '' : 'text-muted' }}">
                                                        <td class="text-small"><code>{{ $key }}</code></td>
                                                        <td class="text-small {{ $changed ? 'bg-light text-danger' : '' }}"
                                                            style="white-space:pre-wrap;word-break:break-word">
                                                            {{ $fmt($before) }}</td>
                                                        <td class="text-small {{ $changed ? 'bg-light text-success' : '' }}"
                                                            style="white-space:pre-wrap;word-break:break-word">
                                                            {{ $fmt($after) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($extra)
                            <div class="card">
                                <div class="card-header">
                                    <h4>Data Tambahan</h4>
                                    <div class="card-header-action">
                                        <button type="button" class="btn btn-sm btn-light" data-toggle="collapse"
                                            data-target="#rawMeta">JSON mentah</button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        @foreach ($extra as $key => $value)
                                            <tr>
                                                <th class="text-small" style="width:200px"><code>{{ $key }}</code>
                                                </th>
                                                <td class="text-small" style="white-space:pre-wrap;word-break:break-word">
                                                    {{ $fmt($value) }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                                <div class="collapse" id="rawMeta">
                                    <div class="card-body border-top">
                                        <pre class="mb-0 text-small" style="white-space:pre-wrap;word-break:break-word">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($related->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Riwayat {{ $log->subjectName() }} #{{ $log->subject_id }}</h4>
                                    <div class="card-header-action">
                                        <a href="{{ route('superadmin.audit-logs', ['subject_type' => $log->subject_type, 'subject_id' => $log->subject_id]) }}"
                                            class="btn btn-sm btn-light">Semua</a>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        @foreach ($related as $r)
                                            <tr>
                                                <td class="text-small text-nowrap">
                                                    {{ $r->created_at->timezone($tz)->format('d M Y H:i') }}</td>
                                                <td><span
                                                        class="badge badge-{{ $r->kindColor() }}">{{ $r->kindLabel() }}</span>
                                                </td>
                                                <td class="text-small">
                                                    {{ \Illuminate\Support\Str::limit($r->description, 120) }}</td>
                                                <td class="text-small text-muted">{{ $r->user->name ?? 'Sistem' }}</td>
                                                <td class="text-right"><a
                                                        href="{{ route('superadmin.audit-logs.show', $r->id) }}"
                                                        class="btn btn-sm btn-light"><i class="fas fa-eye"></i></a></td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Detail</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Waktu</th>
                                        <td class="text-right text-small">
                                            {{ $log->created_at->timezone($tz)->translatedFormat('l, d M Y') }}<br>{{ $log->created_at->timezone($tz)->format('H:i:s') }}
                                            WIB</td>
                                    </tr>
                                    <tr>
                                        <th>Pelaku</th>
                                        <td class="text-right text-small">
                                            @if ($log->user)
                                                {{ $log->user->name }}<br><span class="text-muted">{{ $log->user->email }}
                                                    · {{ $log->user->role }}</span>
                                            @elseif ($log->user_id)
                                                <span class="text-muted">#{{ $log->user_id }} (akun sudah dihapus)</span>
                                            @else
                                                <span class="text-muted">Sistem / scheduler</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>IP</th>
                                        <td class="text-right text-small">{{ $log->ip_address ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Data</th>
                                        <td class="text-right text-small">
                                            @if ($log->subject_type)
                                                @if ($subjectUrl)
                                                    <a href="{{ $subjectUrl }}">{{ $log->subjectName() }}
                                                        #{{ $log->subject_id }} <i
                                                            class="fas fa-external-link-alt"></i></a>
                                                @else
                                                    {{ $log->subjectName() }} #{{ $log->subject_id }}
                                                    <div class="text-muted">tidak ada halaman / sudah dihapus</div>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Tenant</th>
                                        <td class="text-right text-small">
                                            @if ($company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $company->id) }}">{{ $company->name }}</a>
                                            @elseif ($log->companyId())
                                                #{{ $log->companyId() }} <span class="text-muted">(dihapus)</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer">
                                @if ($log->user_id)
                                    <a href="{{ route('superadmin.audit-logs', ['user_id' => $log->user_id]) }}"
                                        class="btn btn-sm btn-light btn-block">Semua log pelaku ini</a>
                                @endif
                                @if ($log->companyId())
                                    <a href="{{ route('superadmin.audit-logs', ['company_id' => $log->companyId()]) }}"
                                        class="btn btn-sm btn-light btn-block">Semua log tenant ini</a>
                                @endif
                                <a href="{{ route('superadmin.audit-logs', ['action' => $log->action]) }}"
                                    class="btn btn-sm btn-light btn-block">Semua aksi <code>{{ $log->action }}</code></a>
                            </div>
                        </div>

                        @if ($nearby->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Aksi Pelaku ±30 Menit</h4>
                                </div>
                                <div class="card-body">
                                    @foreach ($nearby as $n)
                                        <div class="d-flex text-small mb-2">
                                            <span
                                                class="text-muted mr-2 text-nowrap">{{ $n->created_at->timezone($tz)->format('H:i:s') }}</span>
                                            <a href="{{ route('superadmin.audit-logs.show', $n->id) }}"
                                                class="text-truncate"
                                                title="{{ $n->description }}"><code>{{ $n->action }}</code></a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
