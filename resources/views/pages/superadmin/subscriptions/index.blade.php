@extends('layouts.app')

@section('title', 'Langganan Tenant')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Langganan Tenant</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Langganan Tenant</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Langganan Tenant</h2>
                <p class="section-lead">
                    Monitoring status langganan seluruh tenant. Klik Detail untuk perpanjang, ubah paket, atau batalkan
                    secara manual.
                </p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Semua Langganan</h4>
                            </div>
                            <div class="card-body">

                                <div class="float-right">
                                    <form method="GET" action="{{ route('superadmin.subscriptions.index') }}"
                                        class="form-inline">
                                        <select name="status" class="form-control mr-2" onchange="this.form.submit()">
                                            <option value="">Semua status</option>
                                            @foreach (['trial', 'active', 'grace', 'expired', 'cancelled'] as $s)
                                                <option value="{{ $s }}" @selected(request('status') === $s)>
                                                    {{ ucfirst($s) }}</option>
                                            @endforeach
                                        </select>
                                        <select name="plan_id" class="form-control mr-2" onchange="this.form.submit()">
                                            <option value="">Semua paket</option>
                                            @foreach ($plans as $plan)
                                                <option value="{{ $plan->id }}" @selected(request('plan_id') == $plan->id)>
                                                    {{ $plan->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari nama tenant"
                                                name="search" value="{{ request('search') }}">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="clearfix mb-3"></div>

                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Paket</th>
                                                <th>Status</th>
                                                <th>Mulai</th>
                                                <th>Berakhir</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($subscriptions as $sub)
                                                <tr>
                                                    <td>{{ $sub->company->name ?? '-' }}</td>
                                                    <td>{{ $sub->plan->name ?? '-' }}</td>
                                                    <td>
                                                        @php
                                                            $badge = match ($sub->status) {
                                                                'active' => 'success',
                                                                'trial' => 'info',
                                                                'grace' => 'warning',
                                                                'expired', 'cancelled' => 'danger',
                                                                default => 'secondary',
                                                            };
                                                        @endphp
                                                        <div class="badge badge-{{ $badge }}">
                                                            {{ ucfirst($sub->status) }}</div>
                                                    </td>
                                                    <td>{{ optional($sub->started_at)->format('d/m/Y') }}</td>
                                                    <td>{{ optional($sub->expires_at)->format('d/m/Y H:i') }}</td>
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            <a href="{{ route('superadmin.subscriptions.show', $sub->id) }}"
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-eye"></i> Detail
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center">Belum ada data langganan</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $subscriptions->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
