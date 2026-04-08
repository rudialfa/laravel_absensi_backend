@extends('layouts.app')

@section('title', 'Edit Attendance')

@push('style')
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/select2/dist/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}">
@endpush

@section('main')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>Edit Attendance</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                <div class="breadcrumb-item"><a href="{{ route('admin.attendances.index') }}">Attendances</a></div>
                <div class="breadcrumb-item">Edit</div>
            </div>
        </div>

        <div class="section-body">
            <h2 class="section-title">Form Edit Attendance</h2>

            <div class="card">
                <form action="{{ url('admin/attendances/' . $attendance->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="card-header">
                        <h4>Perbarui Data Absensi</h4>
                    </div>

                    <div class="card-body">
                        {{-- USER --}}
                        <div class="form-group">
                            <label>User</label>
                            <select name="user_id" class="form-control @error('user_id') is-invalid @enderror" required>
                                <option value="">-- Pilih User --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ $attendance->user_id == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- TANGGAL --}}
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" name="date"
                                   class="form-control @error('date') is-invalid @enderror"
                                   value="{{ old('date', $attendance->date) }}" required>
                            @error('date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- JAM MASUK --}}
                        <div class="form-group">
                            <label>Jam Masuk</label>
                            <input type="time" name="time_in"
                                   class="form-control @error('time_in') is-invalid @enderror"
                                   value="{{ old('time_in', $attendance->time_in) }}">
                            @error('time_in')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- JAM PULANG --}}
                        <div class="form-group">
                            <label>Jam Pulang</label>
                            <input type="time" name="time_out"
                                   class="form-control @error('time_out') is-invalid @enderror"
                                   value="{{ old('time_out', $attendance->time_out) }}">
                            @error('time_out')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- KOORDINAT MASUK --}}
                        <div class="form-group">
                            <label>Koordinat Masuk</label>
                            <input type="text" name="latlon_in"
                                   class="form-control @error('latlon_in') is-invalid @enderror"
                                   value="{{ old('latlon_in', $attendance->latlon_in) }}"
                                   placeholder="-7.45123,110.21567">
                            @error('latlon_in')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- KOORDINAT PULANG --}}
                        <div class="form-group">
                            <label>Koordinat Pulang</label>
                            <input type="text" name="latlon_out"
                                   class="form-control @error('latlon_out') is-invalid @enderror"
                                   value="{{ old('latlon_out', $attendance->latlon_out) }}"
                                   placeholder="-7.45123,110.21567">
                            @error('latlon_out')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- STATUS --}}
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control @error('status') is-invalid @enderror" required>
                                <option value="on_time" {{ $attendance->status == 'on_time' ? 'selected' : '' }}>On Time</option>
                                <option value="late" {{ $attendance->status == 'late' ? 'selected' : '' }}>Late</option>
                                <option value="absent" {{ $attendance->status == 'absent' ? 'selected' : '' }}>Absent</option>
                                <option value="permission" {{ $attendance->status == 'permission' ? 'selected' : '' }}>Permission</option>
                                <option value="overtime" {{ $attendance->status == 'overtime' ? 'selected' : '' }}>Overtime</option>
                                <option value="guest" {{ $attendance->status == 'guest' ? 'selected' : '' }}>Guest</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- LEMBUR --}}
                        <div class="form-group">
                            <label>Lembur (Menit)</label>
                            <input type="number" name="overtime_minutes"
                                   class="form-control @error('overtime_minutes') is-invalid @enderror"
                                   value="{{ old('overtime_minutes', $attendance->overtime_minutes) }}" min="0">
                            @error('overtime_minutes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- APPROVED --}}
                        <div class="form-group">
                            <label>
                                <input type="hidden" name="approved_overtime" value="0">
                                <input type="checkbox" name="approved_overtime" value="1"
                                       {{ $attendance->approved_overtime ? 'checked' : '' }}>
                                Disetujui Lembur?
                            </label>
                        </div>

                    </div>

                    <div class="card-footer text-right">
                        <button class="btn btn-primary">Update</button>
                        <a href="{{ route('admin.attendances.index') }}" class="btn btn-secondary">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
@endpush
