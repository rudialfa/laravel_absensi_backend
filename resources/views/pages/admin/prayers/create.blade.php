@extends('layouts.app')

@section('title', 'Add Prayers ')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet"
        href="{{ asset('backend/asset/library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/select2/dist/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-tagsinput/dist/bootstrap-tagsinput.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Add Prayers</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Forms</a></div>
                    <div class="breadcrumb-item">Prayers</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Add Prayers</h2>



                <div class="card">
                    <form method="POST" action="{{ route('admin.prayers.store') }}">
                        @csrf

                        <div class="card-header">
                            <h4>Form Create Prayer</h4>
                        </div>

                        <div class="card-body">

                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="city" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="date" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Subuh</label>
                                <input type="time" name="fajr" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Dzuhur</label>
                                <input type="time" name="dzuhur" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Asar</label>
                                <input type="time" name="ashar" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Maghrib</label>
                                <input type="time" name="maghrib" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Isya</label>
                                <input type="time" name="isya" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Source</label>
                                <input type="text" name="source" class="form-control" value="aladhan_api">
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Save</button>
                            <a href="{{ route('admin.prayers.index') }}" class="btn btn-secondary">Back</a>
                        </div>

                    </form>
                </div>

            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
