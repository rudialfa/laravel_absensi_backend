@extends('layouts.app')

@section('title', 'Edit Shifts')

@push('style')
<!-- CSS Libraries -->
<link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-daterangepicker/daterangepicker.css') }}">
<link rel="stylesheet"
    href="{{ asset('backend/asset/library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/asset/library/select2/dist/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
<link rel="stylesheet"
    href="{{ asset('backend/asset/library/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-tagsinput/dist/bootstrap-tagsinput.css') }}">
@endpush

@section('main')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>Edit Shifts</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                <div class="breadcrumb-item"><a href="#">Forms</a></div>
                <div class="breadcrumb-item">Shifts</div>
            </div>
        </div>

        <div class="section-body">
            <h2 class="section-title">Shifts</h2>


            <div class="card">
                <form method="POST" action="{{ route('admin.shifts.update', $shift->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="card-header">
                        <h4>Form Edit Shift</h4>
                    </div>

                    <div class="card-body">

                        <div class="form-group">
                            <label>Pilih Company</label>
                            <select name="company_id" class="form-control" required>
                                @foreach($companies as $c)
                                <option value="{{ $c->id }}" {{ $shift->company_id == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Nama Shift</label>
                            <input type="text" name="name" class="form-control" value="{{ $shift->name }}" required>
                        </div>

                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" class="form-control" value="{{ $shift->start_time }}"
                                required>
                        </div>

                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" class="form-control" value="{{ $shift->end_time }}"
                                required>
                        </div>

                        <div class="form-group">
                            <label>Grace Period (menit)</label>
                            <input type="number" name="grace_period_minutes" class="form-control"
                                value="{{ $shift->grace_period_minutes }}" required>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_default" value="1"
                                    {{ $shift->is_default ? 'checked' : '' }}>
                                Default Shift?
                            </label>
                        </div>

                    </div>

                    <div class="card-footer text-right">
                        <button class="btn btn-primary">Update</button>
                        <a href="{{ route('admin.shifts.index') }}" class="btn btn-secondary">Kembali</a>
                    </div>

                </form>
            </div>


        </div>
    </section>
</div>
@endsection

@push('scripts')
@endpush
