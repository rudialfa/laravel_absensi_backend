@extends('layouts.app')

@section('title', 'Manajement Companies')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Manajement Companies</h1>
                <div class="section-header-button">
                    <a href="{{ route('admin.companies.create') }}" class="btn btn-primary">Add New</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Companies</a></div>
                    <div class="breadcrumb-item">All Companies</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>
                <h2 class="section-title">Companies</h2>
                <p class="section-lead">
                    You can manage all Companies, such as editing, deleting and more.
                </p>


                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>All Posts</h4>
                            </div>
                            <div class="card-body">

                                <div class="float-right">
                                    <form method="GET" action="{{ route('admin.companies.index') }}">
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
                                        <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Alamat</th>
                                <th>Jam Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Zona Waktu</th>
                                <th>Tipe</th>
                                <th>Radius (KM)</th>
                                <th>Aksi</th>
                                        </tr>
                                        @foreach ($companies as $company)
                                            <tr>

                                    <td>
                                        <div class="d-flex align-items-center ">
                                            @if($company->image_url)
                                                <img src="{{ $company->image_url }}" alt="Logo" width="40" height="40" class="rounded-circle mr-2 ">
                                            @else
                                                <img src="https://ui-avatars.com/api/?name={{ urlencode($company->name) }}" width="40" height="40" class="rounded-circle mr-2">
                                            @endif
                                            <span>{{ $company->name }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $company->email ?? '-' }}</td>
                                    <td>{{ Str::limit($company->address, 30) ?? '-' }}</td>
                                    <td>{{ $company->time_in }}</td>
                                    <td>{{ $company->time_out }}</td>
                                    <td>{{ $company->timezone }}</td>
                                    <td>
                                        <span class="badge
                                            @if($company->type == 'company') badge-primary
                                            @elseif($company->type == 'school') badge-success
                                            @else badge-info @endif">
                                            {{ ucfirst($company->type) }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($company->radius_km, 2) }}</td>
                                                <td>
                                                    <div class="d-flex justify-content-center">
                                                        <a href='{{ route('admin.companies.edit', $company->id) }}'
                                                            class="btn btn-sm btn-info btn-icon">
                                                            <i class="fas fa-edit"></i>
                                                            Edit
                                                        </a>

                                                        <form action="{{ route('admin.companies.destroy', $company->id) }}"
                                                            method="POST" class="ml-2">
                                                            <input type="hidden" name="_method" value="DELETE" />
                                                            <input type="hidden" name="_token"
                                                                value="{{ csrf_token() }}" />
                                                            <button class="btn btn-sm btn-danger btn-icon confirm-delete">
                                                                <i class="fas fa-times"></i> Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach


                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $companies->withQueryString()->links() }}
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
