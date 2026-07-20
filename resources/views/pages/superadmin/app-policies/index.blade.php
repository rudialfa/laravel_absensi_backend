@extends('layouts.app')

@section('title', 'Kebijakan Aplikasi')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Kebijakan Aplikasi</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.app-policies.create') }}" class="btn btn-primary">Add New</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Superadmin</a></div>
                    <div class="breadcrumb-item">Kebijakan Aplikasi</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>
                <h2 class="section-title">Privacy Policy, Terms of Service, dll</h2>
                <p class="section-lead">
                    Kelola kebijakan aplikasi. Hanya 1 versi aktif per tipe yang tampil ke tenant/publik —
                    buat draft dulu, lalu publish kalau sudah siap.
                </p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Semua Kebijakan</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>Tipe</th>
                                                <th>Judul</th>
                                                <th>Versi</th>
                                                <th>Status</th>
                                                <th>Dipublish</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($policies as $policy)
                                                <tr>
                                                    <td>{{ str_replace('_', ' ', ucfirst($policy->type)) }}</td>
                                                    <td>{{ $policy->title }}</td>
                                                    <td>{{ $policy->version }}</td>
                                                    <td>
                                                        <span
                                                            class="badge badge-{{ $policy->is_active ? 'success' : 'secondary' }}">
                                                            {{ $policy->is_active ? 'Aktif' : 'Draft' }}
                                                        </span>
                                                    </td>
                                                    <td>{{ optional($policy->published_at)->format('d/m/Y H:i') ?? '-' }}
                                                    </td>
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            @unless ($policy->is_active)
                                                                <a href="{{ route('superadmin.app-policies.edit', $policy->id) }}"
                                                                    class="btn btn-sm btn-info btn-icon">
                                                                    <i class="fas fa-edit"></i> Edit
                                                                </a>

                                                                <form
                                                                    action="{{ route('superadmin.app-policies.publish', $policy->id) }}"
                                                                    method="POST" class="ml-2"
                                                                    onsubmit="return confirm('Publish versi ini? Versi aktif sebelumnya (tipe sama) akan otomatis nonaktif.')">
                                                                    @csrf
                                                                    <button class="btn btn-sm btn-success btn-icon">
                                                                        <i class="fas fa-check"></i> Publish
                                                                    </button>
                                                                </form>

                                                                <form
                                                                    action="{{ route('superadmin.app-policies.destroy', $policy->id) }}"
                                                                    method="POST" class="ml-2">
                                                                    <input type="hidden" name="_method" value="DELETE" />
                                                                    <input type="hidden" name="_token"
                                                                        value="{{ csrf_token() }}" />
                                                                    <button
                                                                        class="btn btn-sm btn-danger btn-icon confirm-delete">
                                                                        <i class="fas fa-times"></i> Delete
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="text-muted small">Versi aktif — buat draft baru
                                                                    untuk mengubah</span>
                                                            @endunless
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center">Belum ada kebijakan</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $policies->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
