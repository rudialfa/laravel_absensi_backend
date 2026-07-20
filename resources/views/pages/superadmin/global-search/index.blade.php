@extends('layouts.app')

@section('title', 'Global Search')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Global Search</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Global Search</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Cari Lintas Tenant</h2>
                <p class="section-lead">
                    Cari cepat tenant, user, atau invoice tanpa harus buka menu satu-satu.
                </p>

                <div class="card mt-4">
                    <div class="card-body">
                        <form method="GET" action="{{ route('superadmin.global-search') }}">
                            <div class="input-group input-group-lg">
                                <input type="text" name="q" value="{{ $keyword }}" class="form-control"
                                    placeholder="Ketik nama tenant, email user, atau nomor invoice..." autofocus>
                                <div class="input-group-append">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($keyword !== '')
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Tenant ({{ $companies->count() }})</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table-striped table">
                                            <thead>
                                                <tr>
                                                    <th>Nama</th>
                                                    <th>Email</th>
                                                    <th>Tipe</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($companies as $company)
                                                    <tr>
                                                        <td>{{ $company->name }}</td>
                                                        <td>{{ $company->email ?? '-' }}</td>
                                                        <td>{{ ucfirst($company->type ?? '-') }}</td>
                                                        <td>
                                                            <div
                                                                class="badge badge-{{ $company->is_active ? 'success' : 'secondary' }}">
                                                                {{ $company->is_active ? 'Aktif' : 'Suspend' }}
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a href="{{ route('superadmin.tenants.show', $company->id) }}"
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-eye"></i> Detail
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center">Tidak ada tenant yang cocok
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-1">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>User ({{ $users->count() }})</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table-striped table">
                                            <thead>
                                                <tr>
                                                    <th>Nama</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Tenant</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($users as $user)
                                                    <tr>
                                                        <td>{{ $user->name }}</td>
                                                        <td>{{ $user->email }}</td>
                                                        <td>{{ ucfirst($user->role) }}</td>
                                                        <td>{{ $user->company->name ?? '-' }}</td>
                                                        <td>
                                                            @if ($user->role !== 'superadmin')
                                                                <form
                                                                    action="{{ route('superadmin.impersonate.start', $user->id) }}"
                                                                    method="POST">
                                                                    @csrf
                                                                    <button class="btn btn-sm btn-secondary btn-icon">
                                                                        <i class="fas fa-user-secret"></i> Impersonate
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center">Tidak ada user yang cocok
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-1">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Invoice ({{ $invoices->count() }})</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table-striped table">
                                            <thead>
                                                <tr>
                                                    <th>No. Invoice</th>
                                                    <th>Tenant</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($invoices as $inv)
                                                    <tr>
                                                        <td>{{ $inv->invoice_number }}</td>
                                                        <td>{{ $inv->company->name ?? '-' }}</td>
                                                        <td>Rp{{ number_format($inv->total_amount, 0, ',', '.') }}</td>
                                                        <td>{{ ucfirst($inv->status) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="text-center">Tidak ada invoice yang cocok
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
