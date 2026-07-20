@extends('layouts.app')

@section('title', 'Paket Langganan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Paket Langganan</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.plans.create') }}" class="btn btn-primary">Tambah Paket</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Plans</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Semua Paket</h2>
                <p class="section-lead">Kelola paket langganan yang ditawarkan ke tenant.</p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Daftar Paket</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Slug</th>
                                                <th>Durasi</th>
                                                <th>Harga</th>
                                                <th>Dipakai</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($plans as $plan)
                                                <tr>
                                                    <td>{{ $plan->name }}</td>
                                                    <td><code>{{ $plan->slug }}</code></td>
                                                    <td>{{ $plan->duration_days }} hari</td>
                                                    <td>
                                                        @if ($plan->is_free)
                                                            <span class="badge badge-secondary">Gratis</span>
                                                        @else
                                                            Rp {{ number_format($plan->price, 0, ',', '.') }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $plan->subscriptions_count }} tenant</td>
                                                    <td>
                                                        @if ($plan->is_active)
                                                            <span class="badge badge-success">Aktif</span>
                                                        @else
                                                            <span class="badge badge-secondary">Nonaktif</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            <a href='{{ route('superadmin.plans.edit', $plan->id) }}'
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-edit"></i>
                                                                Edit
                                                            </a>

                                                            <form
                                                                action="{{ route('superadmin.plans.destroy', $plan->id) }}"
                                                                method="POST" class="ml-2"
                                                                onsubmit="return confirm('Yakin ingin menghapus paket ini?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-sm btn-danger btn-icon">
                                                                    <i class="fas fa-times"></i> Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center">Belum ada paket</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $plans->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
