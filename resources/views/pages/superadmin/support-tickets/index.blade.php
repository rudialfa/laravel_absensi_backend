@extends('layouts.app')

@section('title', 'Tiket Bantuan')

@section('main')
    @php
        $Ctrl = \App\Http\Controllers\Web\SuperAdmin\SuperAdminSupportTicketController::class;
        $filterUrl = fn($extra = []) => route('superadmin.support-tickets.index', $extra);
        $hasFilter = collect(request()->except(['page']))->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
        $typeLabel = ['company' => 'Perusahaan', 'school' => 'Sekolah', 'pesantren' => 'Pesantren'];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Tiket Bantuan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Konten &amp; Dukungan</div>
                    <div class="breadcrumb-item">Tiket Bantuan</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">Pertanyaan &amp; kendala yang dikirim pengguna tenant lewat aplikasi. Tiket aktif diurutkan dari prioritas tertinggi.</p>

                <div class="row">
                    @foreach ([
                        ['Baru', (int) $counts->open, 'info', 'fa-inbox', ['status' => 'open']],
                        ['Diproses', (int) $counts->in_progress, 'warning', 'fa-spinner', ['status' => 'in_progress']],
                        ['Prioritas Tinggi', (int) $counts->high, $counts->high ? 'danger' : 'success', 'fa-fire', ['status' => 'active', 'priority' => 'high']],
                        ['Belum Ditugaskan', (int) $counts->unassigned, $counts->unassigned ? 'secondary' : 'success', 'fa-user-slash', ['status' => 'active', 'assigned' => 'none']],
                    ] as [$label, $value, $color, $icon, $filter])
                        <div class="col-6 col-lg-3">
                            <a href="{{ $filterUrl($filter) }}" class="text-decoration-none">
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></div>
                                    <div class="card-wrap"><div class="card-header"><h4>{{ $label }}</h4></div><div class="card-body" style="font-size:18px">{{ number_format($value) }}</div></div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>

                <div class="mb-3">
                    <a href="{{ $filterUrl(['status' => 'active', 'waiting' => '1']) }}" class="btn btn-sm {{ request('waiting') === '1' ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-reply"></i> Menunggu balasan kita</a>
                    <a href="{{ $filterUrl(['status' => 'active', 'assigned' => 'me']) }}" class="btn btn-sm {{ request('assigned') === 'me' ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-user"></i> Tiket saya ({{ (int) $counts->mine }})</a>
                </div>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.support-tickets.index') }}">
                            <div class="form-row">
                                <div class="col-md-2 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        <option value="active" @selected(request('status') === 'active')>Aktif (baru + diproses)</option>
                                        @foreach ($statuses as $v => $l)
                                            <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="priority" class="form-control">
                                        <option value="">Semua Prioritas</option>
                                        @foreach ($priorities as $v => $l)
                                            <option value="{{ $v }}" @selected(request('priority') === $v)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="category" class="form-control">
                                        <option value="">Semua Kategori</option>
                                        @foreach ($categories as $v => $l)
                                            <option value="{{ $v }}" @selected(request('category') === $v)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="company_id" class="form-control">
                                        <option value="">Semua Tenant</option>
                                        @foreach ($companies as $c)
                                            <option value="{{ $c->id }}" @selected((string) request('company_id') === (string) $c->id)>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="assigned" class="form-control">
                                        <option value="">Semua Penanganan</option>
                                        <option value="me" @selected(request('assigned') === 'me')>Tiket saya</option>
                                        <option value="none" @selected(request('assigned') === 'none')>Belum ditugaskan</option>
                                        @foreach ($staff as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('assigned') === (string) $s->id)>{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md mb-3"><input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="No. / subjek / isi / pengirim / tenant"></div>
                                <div class="col-auto mb-3">
                                    @if (request('waiting'))<input type="hidden" name="waiting" value="1">@endif
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.support-tickets.export', request()->query()) }}" class="btn btn-outline-success" title="Unduh CSV"><i class="fas fa-file-excel"></i></a>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3"><a href="{{ route('superadmin.support-tickets.index') }}" class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr><th style="width:60px">No.</th><th>Subjek</th><th>Pengirim</th><th>Prioritas</th><th>Status</th><th>Ditangani</th><th>Aktivitas</th><th class="text-right">Aksi</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($tickets as $t)
                                        @php
                                            $active = in_array($t->status, ['open', 'in_progress'], true);
                                            $lastFromStaff = $t->last_reply_user_id && $staffIds->contains((int) $t->last_reply_user_id);
                                            $waiting = $active && !$lastFromStaff;
                                            $since = $t->last_reply_at && !$lastFromStaff ? \Carbon\Carbon::parse($t->last_reply_at) : ($lastFromStaff ? null : $t->created_at);
                                            $overdue = $waiting && $since && $since->lt(now()->subHours($Ctrl::SLA_HOURS));
                                        @endphp
                                        <tr class="{{ $active ? '' : 'text-muted' }}">
                                            <td class="text-small">#{{ $t->id }}</td>
                                            <td>
                                                <a href="{{ route('superadmin.support-tickets.show', $t->id) }}" class="font-weight-bold">{{ \Illuminate\Support\Str::limit($t->subject, 60) }}</a>
                                                <div class="text-small text-muted">{{ $categories[$t->category] ?? ucfirst($t->category) }} · {{ $t->public_replies_count }} balasan</div>
                                            </td>
                                            <td>
                                                {{ $t->user->name ?? '-' }}
                                                <div class="text-small text-muted">{{ $t->company->name ?? 'Tanpa tenant' }}{{ $t->company ? ' · ' . ($typeLabel[$t->company->type] ?? $t->company->type) : '' }}</div>
                                            </td>
                                            <td><span class="badge badge-{{ $priorityBadge[$t->priority] ?? 'light' }}">{{ $priorities[$t->priority] ?? $t->priority }}</span></td>
                                            <td>
                                                <span class="badge badge-{{ $statusBadge[$t->status] ?? 'light' }}">{{ $statuses[$t->status] ?? $t->status }}</span>
                                                @if ($waiting)<div class="text-small {{ $overdue ? 'text-danger font-weight-bold' : 'text-warning' }}">{{ $overdue ? 'lewat ' . $Ctrl::SLA_HOURS . ' jam' : 'menunggu balasan' }}</div>@endif
                                            </td>
                                            <td class="text-small">{{ $t->assignedTo->name ?? '-' }}</td>
                                            <td class="text-small text-nowrap" title="{{ optional($t->updated_at)->format('d M Y H:i') }}">
                                                {{ optional($t->updated_at)->diffForHumans() }}
                                                <div class="text-muted">dibuat {{ optional($t->created_at)->format('d M') }}</div>
                                            </td>
                                            <td class="text-right">
                                                <a href="{{ route('superadmin.support-tickets.show', $t->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada tiket.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($tickets->hasPages())
                        <div class="card-footer text-right">{{ $tickets->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
