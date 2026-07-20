@extends('layouts.app')

@section('title', 'Tiket Bantuan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Tiket Bantuan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Superadmin</a></div>
                    <div class="breadcrumb-item">Tiket Bantuan</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>
                <h2 class="section-title">Tiket Bantuan dari Tenant</h2>
                <p class="section-lead">
                    Semua tiket bantuan yang dikirim tenant (HR, ustadz, employee, dst) lewat aplikasi mereka.
                </p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Semua Tiket</h4>
                            </div>
                            <div class="card-body">

                                <div class="float-right">
                                    <form method="GET" action="{{ route('superadmin.support-tickets.index') }}"
                                        class="form-inline">
                                        <select name="status" class="form-control mr-2">
                                            <option value="">Semua Status</option>
                                            @foreach (['open', 'in_progress', 'resolved', 'closed'] as $s)
                                                <option value="{{ $s }}" @selected(request('status') === $s)>
                                                    {{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                                            @endforeach
                                        </select>
                                        <select name="priority" class="form-control mr-2">
                                            <option value="">Semua Prioritas</option>
                                            @foreach (['low', 'medium', 'high'] as $p)
                                                <option value="{{ $p }}" @selected(request('priority') === $p)>
                                                    {{ ucfirst($p) }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari subjek / tenant"
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
                                                <th>Subjek</th>
                                                <th>Prioritas</th>
                                                <th>Status</th>
                                                <th>Ditugaskan Ke</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($tickets as $ticket)
                                                <tr>
                                                    <td>{{ $ticket->company->name ?? '-' }}</td>
                                                    <td>{{ $ticket->subject }}</td>
                                                    <td>
                                                        @php
                                                            $pBadge = match ($ticket->priority) {
                                                                'high' => 'danger',
                                                                'medium' => 'warning',
                                                                default => 'secondary',
                                                            };
                                                        @endphp
                                                        <span
                                                            class="badge badge-{{ $pBadge }}">{{ ucfirst($ticket->priority) }}</span>
                                                    </td>
                                                    <td>
                                                        @php
                                                            $sBadge = match ($ticket->status) {
                                                                'open' => 'info',
                                                                'in_progress' => 'warning',
                                                                'resolved' => 'success',
                                                                default => 'secondary',
                                                            };
                                                        @endphp
                                                        <span
                                                            class="badge badge-{{ $sBadge }}">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
                                                    </td>
                                                    <td>{{ $ticket->assignedTo->name ?? '-' }}</td>
                                                    <td>
                                                        <a href="{{ route('superadmin.support-tickets.show', $ticket->id) }}"
                                                            class="btn btn-sm btn-info btn-icon">
                                                            <i class="fas fa-eye"></i> Lihat
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center">Belum ada tiket bantuan</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $tickets->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
