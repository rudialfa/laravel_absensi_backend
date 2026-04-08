@extends('layouts.app')

@section('title', 'Manajement Prayers')

@push('style')
<!-- CSS Libraries -->
<link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
@endpush

@section('main')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>Manajement Prayers</h1>
            <div class="section-header-button">
                <a href="{{ route('admin.prayers.create') }}" class="btn btn-primary">Add New</a>
            </div>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                <div class="breadcrumb-item"><a href="#">Prayers</a></div>
                <div class="breadcrumb-item">All Prayers</div>
            </div>
        </div>
        <div class="section-body">
            <div class="row">
                <div class="col-12">
                    @include('layouts.alert')
                </div>
            </div>
            <h2 class="section-title">Prayers</h2>
            <p class="section-lead">
                You can manage all Prayers, such as editing, deleting and more.
            </p>


            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>All Posts</h4>
                        </div>
                        <div class="card-body">

                            <div class="float-right">
                                <form method="GET" action="{{ route('admin.prayers.index') }}">
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
                            <th>City</th>
                            <th>Date</th>
                            <th>Subuh</th>
                            <th>Dzuhur</th>
                            <th>Asar</th>
                            <th>Maghrib</th>
                            <th>Isya</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                                   <tbody>
                        @foreach($prayers as $p)
                        <tr>
                            <td>{{ $p->city }}</td>
                            <td>{{ $p->date }}</td>
                            <td>{{ $p->fajr }}</td>
                            <td>{{ $p->dzuhur }}</td>
                            <td>{{ $p->ashar }}</td>
                            <td>{{ $p->maghrib }}</td>
                            <td>{{ $p->isya }}</td>
                             <td>
                                                    <div class="d-flex justify-content-center">
                                                        <a href='{{ route('admin.prayers.edit', $p->id) }}'
                                                            class="btn btn-sm btn-info btn-icon">
                                                            <i class="fas fa-edit"></i>
                                                            Edit
                                                        </a>

                                                        <form action="{{ route('admin.prayers.destroy', $p->id) }}"
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
                    </tbody>
                                </table>


                                </table>
                            </div>
                            <div class="float-right">
                                {{ $prayers->withQueryString()->links() }}
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
