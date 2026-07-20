@extends('layouts.app')

@section('title', 'Staff Superadmin')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Staff Superadmin</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.staff.create') }}" class="btn btn-primary">Tambah Staff</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Staff</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Staff Internal</h2>
                <p class="section-lead">Akun internal yang punya akses penuh ke panel superadmin.</p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Daftar Staff</h4>
                            </div>
                            <div class="card-body">

                                <div class="float-right">
                                    <form method="GET" action="{{ route('superadmin.staff.index') }}">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari nama"
                                                name="name" value="{{ request('name') }}">
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
                                                <th>Nama</th>
                                                <th>Email</th>
                                                <th>Telepon</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($staff as $s)
                                                <tr>
                                                    <td>{{ $s->name }}</td>
                                                    <td>{{ $s->email }}</td>
                                                    <td>{{ $s->phone ?? '-' }}</td>
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            <a href='{{ route('superadmin.staff.edit', $s->id) }}'
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-edit"></i>
                                                                Edit
                                                            </a>

                                                            <form action="{{ route('superadmin.staff.destroy', $s->id) }}"
                                                                method="POST" class="ml-2"
                                                                onsubmit="return confirm('Yakin ingin menghapus staff ini?');">
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
                                                    <td colspan="4" class="text-center">Belum ada staff</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $staff->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
