@extends('layouts.app')

@section('title', 'Izin Asrama')

@section('main')
    @php
        $Ctrl = \App\Http\Controllers\Web\SuperAdmin\Boarding\SuperAdminBoardingPermissionController::class;
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $viewUrl = fn($v, $extra = []) => route(
            'superadmin.boarding.permissions.index',
            array_filter(
                ['view' => $v, 'company_id' => request('company_id')] + $extra,
                fn($x) => $x !== null && $x !== '',
            ),
        );
        $hasFilter = collect(request()->except(['view', 'page']))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();
        $studentUrl = fn($id) => Route::has('superadmin.school.students.show')
            ? route('superadmin.school.students.show', $id)
            : null;
        $viewIcon = [
            'all' => 'fa-list',
            'pending' => 'fa-hourglass-half',
            'outside' => 'fa-walking',
            'late' => 'fa-exclamation-triangle',
        ];
        $viewColor = ['all' => 'primary', 'pending' => 'warning', 'outside' => 'info', 'late' => 'danger'];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Izin Asrama</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Pesantren &amp; Asrama</div>
                    <div class="breadcrumb-item">Izin Asrama</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">Izin pulang / keluar santri yang diajukan wali dan diproses pengasuh pondok. Pantau
                    terutama santri yang <b>terlambat kembali</b>.</p>

                <div class="row">
                    @foreach ($views as $v => $label)
                        <div class="col-6 col-lg-3">
                            <a href="{{ $viewUrl($v) }}" class="text-decoration-none">
                                <div
                                    class="card card-statistic-1 {{ $view === $v ? 'border border-' . $viewColor[$v] : '' }}">
                                    <div
                                        class="card-icon bg-{{ $v === 'late' && !$counts[$v] ? 'success' : $viewColor[$v] }}">
                                        <i class="fas {{ $viewIcon[$v] }}"></i>
                                    </div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>{{ $label }}</h4>
                                        </div>
                                        <div class="card-body" style="font-size:18px">{{ number_format($counts[$v]) }}</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.boarding.permissions.index') }}">
                            <input type="hidden" name="view" value="{{ $view }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control" onchange="this.form.submit()">
                                        <option value="">Semua Pesantren / Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="jenis" class="form-control">
                                        <option value="">Pulang &amp; Keluar</option>
                                        @foreach ($jenis as $v => $l)
                                            <option value="{{ $v }}" @selected(request('jenis') === $v)>
                                                {{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($view === 'all')
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua Status</option>
                                            @foreach ($statusLabels as $v => $l)
                                                <option value="{{ $v }}" @selected(request('status') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md-2 mb-3"><input type="date" name="date_from"
                                        value="{{ request('date_from') }}" class="form-control"
                                        title="Tanggal keluar dari"></div>
                                <div class="col-md-2 mb-3"><input type="date" name="date_to"
                                        value="{{ request('date_to') }}" class="form-control"
                                        title="Tanggal keluar sampai"></div>
                                <div class="col-md mb-3"><input type="text" name="search"
                                        value="{{ request('search') }}" class="form-control"
                                        placeholder="Nama / NIS santri, penjemput"></div>
                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.boarding.permissions.export', request()->query() + ['view' => $view]) }}"
                                        class="btn btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.boarding.permissions.index', ['view' => $view]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Santri</th>
                                        <th>Izin</th>
                                        <th>Keluar → Kembali</th>
                                        <th>Penjemput</th>
                                        <th>Status</th>
                                        <th class="text-right" style="width:90px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $p)
                                        @php
                                            [$stateLabel, $stateBadge] = $Ctrl::state($p);
                                            $st = $p->student;
                                            $room = $st?->activeRoomAssignment?->room;
                                        @endphp
                                        <tr>
                                            <td>
                                                @if ($st)
                                                    @if ($studentUrl($st->id))
                                                        <a href="{{ $studentUrl($st->id) }}">{{ $st->name }}</a>
                                                    @else
                                                        {{ $st->name }}
                                                    @endif
                                                    <div class="text-small text-muted">
                                                        {{ $st->classRoom->name ?? '-' }}{{ $room ? ' · ' . ($room->dormitory->name ?? '') . ' ' . $room->name : '' }}
                                                    </div>
                                                    <div class="text-small text-muted">
                                                        {{ $companyNames[$p->company_id] ?? '' }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span
                                                    class="badge badge-{{ $p->jenis === 'izin_pulang' ? 'primary' : 'info' }}">{{ $jenis[$p->jenis] ?? $p->jenis }}</span>
                                                <div class="text-small">
                                                    {{ \Illuminate\Support\Str::limit($p->alasan, 50) }}</div>
                                            </td>
                                            <td class="text-nowrap">
                                                {{ $fmtDate($p->tanggal_keluar) }} →
                                                {{ $fmtDate($p->tanggal_kembali_rencana) }}
                                                @if ($p->tanggal_kembali_aktual)
                                                    <div class="text-small text-success">kembali
                                                        {{ $fmtDate($p->tanggal_kembali_aktual) }}</div>
                                                @endif
                                            </td>
                                            <td class="text-small">
                                                {{ $p->nama_penjemput ?: '-' }}
                                                @if ($p->hubungan_penjemput)
                                                    <span class="text-muted">({{ $p->hubungan_penjemput }})</span>
                                                @endif
                                                <div class="text-muted">{{ $p->kontak_penjemput }}</div>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $stateBadge }}">{{ $stateLabel }}</span>
                                                @if ($p->reviewedBy)
                                                    <div class="text-small text-muted">{{ $p->reviewedBy->name }}</div>
                                                @endif
                                            </td>
                                            <td class="text-right text-nowrap">
                                                <a href="{{ route('superadmin.boarding.permissions.show', $p->id) }}"
                                                    class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-toggle="modal" data-target="#schoolActionModal"
                                                    data-action="{{ route('superadmin.boarding.permissions.destroy', $p->id) }}"
                                                    data-label="Hapus izin {{ $st->name ?? '' }} ({{ $fmtDate($p->tanggal_keluar) }})"><i
                                                        class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Tidak ada data izin
                                                asrama.</td>
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
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
