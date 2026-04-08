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
        <div class="card-body">
            <form action="{{ route('admin.permissions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

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
                    <label>Tanggal Izin</label>
                    <input type="date" name="date_permission" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Alasan</label>
                    <textarea name="reason" class="form-control" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label>Bukti (Opsional)</label>
                    <input type="file" name="image" class="form-control">
                </div>

                <div class="form-group">
                    <label>
                        <input type="hidden" name="is_approved" value="0">
                        <input type="checkbox" name="is_approved" value="1">
                        Disetujui?
                    </label>
                </div>

                <button class="btn btn-primary">Simpan</button>
            </form>
        </div>
    </div>

            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
