@extends('layouts.app')

@section('title', 'Add Schedules ')

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
                <h1>Add Schedules</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Forms</a></div>
                    <div class="breadcrumb-item">Schedules</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Add Schedules</h2>



                <div class="card">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('admin.schedules.store') }}">
                        @csrf

                        <div class="card-header">
                            <h4>Form Create Schedule</h4>
                        </div>

                        <div class="card-body">

                            {{-- USER --}}
                            <div class="form-group">
                                <label>User</label>
                                <select name="user_id" class="form-control" required>
                                    <option value="">-- Pilih User --</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- TITLE --}}
                            <div class="form-group">
                                <label>Judul</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>

                            {{-- DESCRIPTION --}}
                            <div class="form-group">
                                <label>Deskripsi</label>
                                <textarea name="description" class="form-control"></textarea>
                            </div>

                            {{-- DATETIME --}}
                            <div class="form-group">
                                <label>Waktu Mulai</label>
                                <input type="datetime-local" name="start_datetime" class="form-control" required>
                            </div>

                            {{-- REMINDER OFFSETS --}}
                            <div class="form-group">
                                <label>Pengingat (menit) — pisahkan dengan koma</label>
                                <input type="text" name="reminder_offsets" class="form-control"
                                    placeholder="contoh: 5,15,60">
                            </div>

                            {{-- LOCATION --}}
                            <div class="form-group">
                                <label>Lokasi (JSON)</label>
                                {{-- <textarea name="location" class="form-control" placeholder='{"lat": -7.12, "lng": 110.22}'></textarea> --}}
                                {{-- code textarea versi 2 --}}
                                <textarea name="location" class="form-control" placeholder='{"lat": -7.12, "lng": 110.22}'></textarea>
                            </div>

                            {{-- IS TASK DUTY --}}
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_task_duty"> Tugas Penting?
                                </label>
                            </div>

                            {{-- STATUS --}}
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="upcoming">Upcoming</option>
                                    <option value="done">Done</option>
                                    <option value="canceled">Canceled</option>
                                </select>
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Simpan</button>
                        </div>

                    </form>
                </div>

            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
