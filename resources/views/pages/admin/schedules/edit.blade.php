@extends('layouts.app')

@section('title', 'Edit Schedules')

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
                <h1>Edit Schedules</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Forms</a></div>
                    <div class="breadcrumb-item">Schedules</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Schedules</h2>


                <div class="card">
                    <form method="POST" action="{{ route('admin.schedules.update', $schedule->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="card-header">
                            <h4>Form Edit Schedule</h4>
                        </div>

                        <div class="card-body">

                            {{-- USER --}}
                            <div class="form-group">
                                <label>User</label>
                                <select name="user_id" class="form-control" required>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}"
                                            {{ $schedule->user_id == $u->id ? 'selected' : '' }}>
                                            {{ $u->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- TITLE --}}
                            <div class="form-group">
                                <label>Judul</label>
                                <input type="text" name="title" class="form-control" value="{{ $schedule->title }}"
                                    required>
                            </div>

                            {{-- DESCRIPTION --}}
                            <div class="form-group">
                                <label>Deskripsi</label>
                                <textarea name="description" class="form-control">{{ $schedule->description }}</textarea>
                            </div>

                            {{-- START DATETIME --}}
                            <div class="form-group">
                                <label>Waktu Mulai</label>
                                <input type="datetime-local" name="start_datetime" class="form-control"
                                    value="{{ date('Y-m-d\TH:i', strtotime($schedule->start_datetime)) }}" required>
                            </div>

                            {{-- REMINDER OFFSETS --}}
                            <div class="form-group">
                                <label>Pengingat (menit, pisahkan koma)</label>

                                @php
                                    $offsets = is_array($schedule->reminder_offsets)
                                        ? implode(',', $schedule->reminder_offsets)
                                        : '';
                                @endphp

                                <input type="text" name="reminder_offsets" class="form-control"
                                    value="{{ $offsets }}" placeholder="contoh: 5,15,60">
                            </div>

                            {{-- LOCATION --}}
                            <div class="form-group">
                                <label>Lokasi (JSON)</label>
                                {{-- <textarea name="location" class="form-control" placeholder='{"lat": -7.12, "lng": 110.22}'>
{{ json_encode($schedule->location) }}
                        </textarea> --}}

                                {{-- code text area versi 2 --}}
                                <textarea name="location" class="form-control">{{ json_encode($schedule->location) }}</textarea>
                            </div>

                            {{-- TASK DUTY --}}
                            <div class="form-group">
                                <label>
                                    <input type="hidden" name="is_task_duty" value="0">
                                    <input type="checkbox" name="is_task_duty" value="1"
                                        {{ $schedule->is_task_duty ? 'checked' : '' }}>
                                    Tugas Penting?
                                </label>
                            </div>

                            {{-- STATUS --}}
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="upcoming" {{ $schedule->status == 'upcoming' ? 'selected' : '' }}>
                                        Upcoming</option>
                                    <option value="done" {{ $schedule->status == 'done' ? 'selected' : '' }}>Done
                                    </option>
                                    <option value="canceled" {{ $schedule->status == 'canceled' ? 'selected' : '' }}>
                                        Canceled</option>
                                </select>
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Update</button>
                        </div>

                    </form>
                </div>

            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
