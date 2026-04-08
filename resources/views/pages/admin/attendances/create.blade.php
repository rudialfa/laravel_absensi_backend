@extends('layouts.app')

@section('title', 'Add Attendances ')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/select2/dist/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-tagsinput/dist/bootstrap-tagsinput.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Add Attendances</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Forms</a></div>
                    <div class="breadcrumb-item">Attendances</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Add Attendances</h2>



                <div class="card">
                    <form action="{{ route('admin.attendances.store') }}" method="POST">
                        @csrf
                        <div class="card-header">
                            <h4>Input Text</h4>
                        </div>
                        <div class="card-body">
                               <div class="form-group">
                    <label>User</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">-- Pilih User --</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Jam Masuk</label>
                    <input type="time" name="time_in" class="form-control">
                </div>

                <div class="form-group">
                    <label>Jam Pulang</label>
                    <input type="time" name="time_out" class="form-control">
                </div>

                <div class="form-group">
                    <label>Koordinat Masuk</label>
                    <input type="text" name="latlon_in" class="form-control" placeholder="-7.45123,110.21567">
                </div>

                <div class="form-group">
                    <label>Koordinat Pulang</label>
                    <input type="text" name="latlon_out" class="form-control" placeholder="-7.45123,110.21567">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="on_time">On Time</option>
                        <option value="late">Late</option>
                        <option value="absent">Absent</option>
                        <option value="permission">Permission</option>
                        <option value="overtime">Overtime</option>
                        <option value="guest">Guest</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Lembur (Menit)</label>
                    <input type="number" name="overtime_minutes" class="form-control" value="0" min="0">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="approved_overtime" value="1">
                        Disetujui Lembur?
                    </label>
                </div>

                        </div>
                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Submit</button>
                        </div>
                    </form>
                </div>

            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
