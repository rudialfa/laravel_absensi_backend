@extends('layouts.app')

@section('title', 'Izin, Cuti & Lembur')

@section('main')
    @php
        $Ctrl = \App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminRequestController::class;
        $keep = request()->only(['company_id', 'search', 'from', 'to']);
        $hasFilter = collect(request()->only(['company_id', 'search', 'from', 'to', 'status', 'type', 'stale']))
            ->filter()
            ->isNotEmpty();
        $statusOptions = $tab === 'permissions' ? array_diff_key($statusLabels, ['canceled' => 1]) : $statusLabels;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Izin, Cuti &amp; Lembur</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring</div>
                    <div class="breadcrumb-item">Pengajuan</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Pengajuan karyawan dari semua perusahaan. Persetujuan dilakukan HR masing-masing perusahaan lewat
                    aplikasi.
                    Di sini Anda bisa memantau pengajuan yang lama tidak diproses dan menghapus data yang salah.
                </p>

                {{-- Peringatan pengajuan yang lama tidak diproses --}}
                @php $totalStale = array_sum($stalePerTab); @endphp
                @if ($totalStale > 0)
                    <div class="alert alert-warning">
                        <i class="fas fa-hourglass-half"></i>
                        {{ $totalStale }} pengajuan sudah lebih dari {{ $staleDays }} hari belum diproses HR:
                        @foreach ($stalePerTab as $t => $n)
                            @if ($n)
                                <a href="{{ route('superadmin.monitoring.requests.index', ['tab' => $t, 'stale' => 1]) }}"
                                    class="alert-link ml-1">{{ $tabs[$t] }} ({{ $n }})</a>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Tab --}}
                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ route('superadmin.monitoring.requests.index', array_merge($keep, ['tab' => $key])) }}">
                                {{ $label }}
                                @if ($pendingPerTab[$key] > 0)
                                    <span class="badge badge-warning">{{ $pendingPerTab[$key] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{-- Status (klik = filter) --}}
                <div class="mb-3">
                    <a href="{{ route('superadmin.monitoring.requests.index', array_merge($keep, ['tab' => $tab, 'type' => request('type')])) }}"
                        class="btn btn-sm mb-1 {{ !request('status') && !request('stale') ? 'btn-primary' : 'btn-outline-primary' }}">
                        Semua <span class="badge badge-light">{{ $statusCounts->sum() }}</span>
                    </a>
                    @foreach ($statusOptions as $key => $label)
                        <a href="{{ route('superadmin.monitoring.requests.index', array_merge($keep, ['tab' => $tab, 'status' => $key, 'type' => request('type')])) }}"
                            class="btn btn-sm mb-1 {{ request('status') === $key ? 'btn-' . $badge[$key] : 'btn-outline-' . $badge[$key] }}">
                            {{ $label }} <span class="badge badge-light">{{ $statusCounts[$key] ?? 0 }}</span>
                        </a>
                    @endforeach
                    @if (request('stale'))
                        <span class="badge badge-warning ml-1">Hanya yang > {{ $staleDays }} hari belum diproses</span>
                    @endif
                </div>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.monitoring.requests.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            @if (request('status'))
                                <input type="hidden" name="status" value="{{ request('status') }}">
                            @endif
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control">
                                        <option value="">Semua Perusahaan</option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>
                                                {{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        class="form-control" placeholder="Nama karyawan atau alasan">
                                </div>
                                @if (!empty($typeOptions))
                                    <div class="col-md-2 mb-3">
                                        <select name="type" class="form-control">
                                            <option value="">Semua Jenis</option>
                                            @foreach ($typeOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(request('type') === $value)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md-{{ empty($typeOptions) ? 2 : 1 }} mb-3">
                                    <input type="date" name="from" value="{{ request('from') }}"
                                        class="form-control px-1" title="Dari tanggal">
                                </div>
                                <div class="col-md-{{ empty($typeOptions) ? 2 : 1 }} mb-3">
                                    <input type="date" name="to" value="{{ request('to') }}"
                                        class="form-control px-1" title="Sampai tanggal">
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block" title="Cari"><i
                                            class="fas fa-search"></i></button>
                                </div>
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('superadmin.monitoring.requests.export', request()->query() + ['tab' => $tab]) }}"
                                        class="btn btn-outline-success btn-block" title="Unduh CSV">
                                        <i class="fas fa-file-excel"></i>
                                    </a>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3">
                                    <a href="{{ route('superadmin.monitoring.requests.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a>
                                </div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Karyawan</th>
                                        <th>Jenis</th>
                                        <th>Tanggal</th>
                                        <th>Alasan</th>
                                        <th>Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $item)
                                        @php
                                            $status = $Ctrl::statusOf($tab, $item);
                                            [$start, $end, $time] = $Ctrl::periodOf($tab, $item);
                                            $isStale =
                                                $status === 'pending' &&
                                                $item->created_at &&
                                                $item->created_at->lt(now()->subDays($staleDays));
                                            $file = $Ctrl::fileOf($tab, $item);
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $item->user->name ?? '(user terhapus)' }}</strong>
                                                <div class="text-small text-muted">
                                                    {{ $companyNames[$item->company_id] ?? '-' }}{{ $item->user && $item->user->department ? ' · ' . $item->user->department : '' }}
                                                </div>
                                            </td>
                                            <td>
                                                {{ $Ctrl::typeLabel($tab, $item) }}
                                                @if ($tab === 'overtimes' && $item->minutes)
                                                    <div class="text-small text-muted">{{ $item->duration_label }}</div>
                                                @endif
                                            </td>
                                            <td class="text-small">
                                                {{ \Carbon\Carbon::parse($start)->format('d M Y') }}
                                                @if ($end && $end !== $start)
                                                    – {{ \Carbon\Carbon::parse($end)->format('d M Y') }}
                                                @endif
                                                @if ($time)
                                                    <div class="text-muted">{{ $time }}</div>
                                                @endif
                                            </td>
                                            <td class="text-small" style="max-width:260px">
                                                {{ \Illuminate\Support\Str::limit($item->reason, 80) ?: '-' }}
                                                @if ($file)
                                                    <div><i class="fas fa-paperclip text-muted"></i> lampiran</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span
                                                    class="badge badge-{{ $badge[$status] ?? 'secondary' }}">{{ $statusLabels[$status] ?? $status }}</span>
                                                @if ($isStale)
                                                    <div class="text-small text-danger"><i
                                                            class="fas fa-hourglass-half"></i>
                                                        {{ $item->created_at->diffForHumans() }}</div>
                                                @elseif ($item->approver)
                                                    <div class="text-small text-muted">oleh {{ $item->approver->name }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.monitoring.requests.show', [$tab, $item->id]) }}"
                                                    class="btn btn-sm btn-info" title="Detail"><i
                                                        class="fas fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Tidak ada pengajuan
                                                {{ strtolower($tabs[$tab]) }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($items->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">Menampilkan {{ $items->firstItem() }}–{{ $items->lastItem() }} dari
                                {{ $items->total() }}</small>
                            {{ $items->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
