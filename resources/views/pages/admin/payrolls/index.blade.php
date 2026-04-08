@extends('layouts.app')

@section('title', 'Manajement Payrolls')

@push('style')
<!-- CSS Libraries -->
<link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
@endpush

@section('main')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>Manajement Payrolls</h1>
            <div class="section-header-button">
                <a href="{{ route('admin.payrools.create') }}" class="btn btn-primary">Add New</a>
            </div>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                <div class="breadcrumb-item"><a href="#">Payrolls</a></div>
                <div class="breadcrumb-item">All Payrolls</div>
            </div>
        </div>
        <div class="section-body">
            <div class="row">
                <div class="col-12">
                    @include('layouts.alert')
                </div>
            </div>
            <h2 class="section-title">Payrolls</h2>
            <p class="section-lead">
                You can manage all Payrolls, such as editing, deleting and more.
            </p>


            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>All Posts</h4>
                        </div>
                        <div class="card-body">

                            <div class="float-right">
                                <form method="GET" action="{{ route('admin.payrools.index') }}">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Search" name="name">
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
                                            <th>#</th>
                                            <th>Nama User</th>
                                            <th>Periode</th>
                                            <th>Gaji Pokok</th>
                                            <th>Total Bersih</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @forelse($payrolls as $index => $p)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $p->user->name ?? '-' }}</td>
                                            <td>{{ $p->period_start }} s/d {{ $p->period_end }}</td>
                                            <td>Rp{{ number_format($p->base_salary, 0, ',', '.') }}</td>
                                            <td><b>Rp{{ number_format($p->net_pay, 0, ',', '.') }}</b></td>
                                            <td>
                                                <span
                                                    class="badge badge-{{ $p->status == 'paid' ? 'success' : ($p->status == 'approved' ? 'warning' : 'secondary') }}">
                                                    {{ ucfirst($p->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                    <a href='{{ route('admin.payrools.edit', $p->id) }}'
                                                        class="btn btn-sm btn-info btn-icon">
                                                        <i class="fas fa-edit"></i>
                                                        Edit
                                                    </a>

                                                    <form action="{{ route('admin.payrools.destroy', $p->id) }}" method="POST"
                                                        class="ml-2">
                                                        <input type="hidden" name="_method" value="DELETE" />
                                                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                                        <button class="btn btn-sm btn-danger btn-icon confirm-delete">
                                                            <i class="fas fa-times"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="text-center">Belum ada data payroll</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>


                                </table>
                            </div>
                            <div class="float-right">
                                {{ $payrolls->withQueryString()->links() }}
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
<!-- JS Libraies -->
<script src="{{ asset('backend/asset/library/selectric/public/jquery.selectric.min.js') }}"></script>

<!-- Page Specific JS File -->
<script src="{{ asset('backend/asset/js/page/features-posts.js') }}"></script>
@endpush
