@extends('layouts.app')

@section('title', 'Langganan Tenant')

@section('main')
    @php
        $badge = ['trial' => 'info', 'active' => 'success', 'grace' => 'warning', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $total = $counts->sum();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Langganan Tenant</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.subscriptions.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Buat Langganan</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Langganan</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($withoutSubCount > 0)
                    <div class="alert alert-light border">
                        <i class="fas fa-info-circle text-primary"></i>
                        {{ $withoutSubCount }} tenant belum punya langganan sama sekali (aplikasinya terkunci).
                        <a href="{{ route('superadmin.subscriptions.create') }}">Buatkan langganan</a> atau
                        <a href="{{ route('superadmin.tenants.index', ['subscription' => 'none']) }}">lihat daftarnya</a>.
                    </div>
                @endif

                {{-- Ringkasan status (klik = filter) --}}
                <div class="mb-3">
                    <a href="{{ route('superadmin.subscriptions.index', request()->except(['status', 'expiring', 'page'])) }}"
                        class="btn btn-sm mb-1 {{ !request('status') && !request('expiring') ? 'btn-primary' : 'btn-outline-primary' }}">
                        Semua <span class="badge badge-light">{{ $total }}</span>
                    </a>
                    @foreach ($statuses as $key => $label)
                        <a href="{{ route('superadmin.subscriptions.index', array_merge(request()->except(['status', 'expiring', 'page']), ['status' => $key])) }}"
                            class="btn btn-sm mb-1 {{ request('status') === $key ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $label }} <span class="badge badge-light">{{ $counts[$key] ?? 0 }}</span>
                        </a>
                    @endforeach
                    <a href="{{ route('superadmin.subscriptions.index', ['expiring' => 7]) }}"
                        class="btn btn-sm mb-1 {{ request('expiring') ? 'btn-warning' : 'btn-outline-warning' }}">
                        <i class="fas fa-hourglass-half"></i> Berakhir ≤ 7 hari
                    </a>
                </div>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.subscriptions.index') }}">
                            @foreach (['status', 'expiring'] as $keep)
                                @if (request($keep))
                                    <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">
                                @endif
                            @endforeach
                            <div class="form-row">
                                <div class="col-md-4 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Nama atau email tenant">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="plan_id" class="form-control">
                                        <option value="">Semua Paket</option>
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="type" class="form-control">
                                        <option value="">Semua Jenis</option>
                                        @foreach ($typeLabels as $value => $label)
                                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <select name="sort" class="form-control">
                                        <option value="expires" @selected(request('sort', 'expires') === 'expires')>Berakhir paling dekat</option>
                                        <option value="expires_desc" @selected(request('sort') === 'expires_desc')>Berakhir paling lama</option>
                                        <option value="newest" @selected(request('sort') === 'newest')>Terbaru dibuat</option>
                                    </select>
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block" title="Cari"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Paket</th>
                                        <th>Status</th>
                                        <th>Mulai</th>
                                        <th>Berakhir</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($subscriptions as $sub)
                                        @php
                                            $days = in_array($sub->status, ['trial', 'active', 'grace']) ? $sub->daysRemaining() : null;
                                        @endphp
                                        <tr>
                                            <td>
                                                @if ($sub->company)
                                                    <a href="{{ route('superadmin.subscriptions.show', $sub->id) }}" class="font-weight-bold">{{ $sub->company->name }}</a>
                                                    <div class="text-small text-muted">
                                                        {{ $typeLabels[$sub->company->type] ?? ucfirst($sub->company->type) }}
                                                        @unless ($sub->company->is_active)
                                                            · <span class="text-danger">disuspend</span>
                                                        @endunless
                                                    </div>
                                                @else
                                                    <span class="text-muted">Tenant terhapus</span>
                                                @endif
                                            </td>
                                            <td>{{ $sub->plan->name ?? '-' }}</td>
                                            <td><span class="badge badge-{{ $badge[$sub->status] ?? 'secondary' }}">{{ $statuses[$sub->status] ?? ucfirst($sub->status) }}</span></td>
                                            <td>{{ optional($sub->started_at)->format('d M Y') }}</td>
                                            <td>
                                                {{ optional($sub->expires_at)->format('d M Y H:i') }}
                                                @if ($days !== null)
                                                    <div class="text-small {{ $days <= 3 ? 'text-danger' : 'text-muted' }}">
                                                        {{ $sub->status === 'grace' ? 'terkunci dalam' : 'sisa' }} {{ $days }} hari
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.subscriptions.show', $sub->id) }}" class="btn btn-sm btn-info" title="Detail & kelola"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('superadmin.subscriptions.edit', $sub->id) }}" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada langganan yang cocok</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($subscriptions->hasPages())
                        <div class="card-footer text-right">{{ $subscriptions->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
