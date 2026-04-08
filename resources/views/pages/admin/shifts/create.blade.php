@extends('layouts.app')

@section('title', 'Add Shifts ')

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
            <h1>Add Shifts</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                <div class="breadcrumb-item"><a href="#">Forms</a></div>
                <div class="breadcrumb-item">Shifts</div>
            </div>
        </div>

        <div class="section-body">
            <h2 class="section-title">Add Shifts</h2>


            <div class="card">
                <form method="POST" action="{{ route('admin.shifts.store') }}">
                    @csrf

                    <div class="card-header">
                        <h4>Form Add Shift</h4>
                    </div>

                    <div class="card-body">

                        <div class="form-group">
                            <label>Pilih Company</label>
                            <select name="company_id" class="form-control" required>
                                <option value="">-- Pilih Company --</option>
                                @foreach($companies as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Nama Shift</label>
                            <input type="text" name="name" class="form-control" required
                                placeholder="Shift Pagi / Shift Malam">
                        </div>

                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Grace Period (menit)</label>
                            <input type="number" name="grace_period_minutes" class="form-control" required min="0"
                                value="15">
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_default" value="1"> Jadikan Default Shift?
                            </label>
                        </div>

                    </div>

                    <div class="card-footer text-right">
                        <button class="btn btn-primary">Submit</button>
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
