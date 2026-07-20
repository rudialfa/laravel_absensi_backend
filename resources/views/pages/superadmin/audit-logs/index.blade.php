@extends('layouts.app')

@section('title', 'Audit Log')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Audit Log</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Audit Logs</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Riwayat Aktivitas Superadmin</h2>
                <p class="section-lead">Catatan semua aksi penting yang dilakukan lewat panel superadmin.</p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Log Aktivitas</h4>
                            </div>
                            <div class="card-body">

                                <form method="GET" action="{{ route('superadmin.audit-logs') }}" class="form-row mb-3">
                                    <div class="col-md-4 mb-2">
                                        <input type="text" name="action" value="{{ request('action') }}"
                                            class="form-control" placeholder="Cari aksi, contoh: suspend_tenant">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <button class="btn btn-primary btn-block"><i class="fas fa-search"></i>
                                            Cari</button>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>Waktu</th>
                                                <th>Oleh</th>
                                                <th>Aksi</th>
                                                <th>Deskripsi</th>
                                                <th>IP</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($logs as $log)
                                                <tr>
                                                    <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                                                    <td>{{ $log->user->name ?? 'System' }}</td>
                                                    <td><span class="badge badge-secondary">{{ $log->action }}</span></td>
                                                    <td>{{ $log->description }}</td>
                                                    <td>{{ $log->ip_address }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">Belum ada log</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $logs->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
