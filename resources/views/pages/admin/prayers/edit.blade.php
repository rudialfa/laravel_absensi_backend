@extends('layouts.app')

@section('title', 'Edit Prayers')

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
                <h1>Edit Prayers</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Forms</a></div>
                    <div class="breadcrumb-item">Prayers</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Prayers</h2>


                <div class="card">
                    <form method="POST" action="{{ route('admin.prayers.update', $prayer->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="card-header">
                            <h4>Form Edit Prayer</h4>
                        </div>

                        <div class="card-body">

                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="city" class="form-control" value="{{ $prayer->city }}"
                                    required>
                            </div>

                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="date" class="form-control" value="{{ $prayer->date }}"
                                    required>
                            </div>

                            <div class="form-group">
                                <label>Subuh</label>
                                <input type="time" name="fajr" class="form-control"
                                    value="{{ $prayer->fajr ? date('H:i', strtotime($prayer->fajr)) : '' }}">
                            </div>

                            <div class="form-group">
                                <label>Dzuhur</label>
                                <input type="time" name="dzuhur" class="form-control"
                                    value="{{ $prayer->dzuhur ? date('H:i', strtotime($prayer->dzuhur)) : '' }}">
                            </div>

                            <div class="form-group">
                                <label>Asar</label>
                                <input type="time" name="ashar" class="form-control"
                                    value="{{ $prayer->ashar ? date('H:i', strtotime($prayer->ashar)) : '' }}">
                            </div>

                            <div class="form-group">
                                <label>Maghrib</label>
                                <input type="time" name="maghrib" class="form-control"
                                    value="{{ $prayer->maghrib ? date('H:i', strtotime($prayer->maghrib)) : '' }}">
                            </div>

                            <div class="form-group">
                                <label>Isya</label>
                                <input type="time" name="isya" class="form-control"
                                    value="{{ $prayer->isya ? date('H:i', strtotime($prayer->isya)) : '' }}">
                            </div>

                            <div class="form-group">
                                <label>Source</label>
                                <input type="text" name="source" class="form-control" value="{{ $prayer->source }}">
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Update</button>
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
