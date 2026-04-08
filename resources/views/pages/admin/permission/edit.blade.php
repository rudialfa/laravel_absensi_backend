@extends('layouts.app')

@section('title', 'Edit Permission')

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
                <div class="breadcrumb-item"><a href="{{ route('admin.permissions.index') }}">Permissions</a></div>
                <div class="breadcrumb-item">Edit</div>
            </div>
        </div>

        <div class="section-body">
            <h2 class="section-title">Form Edit Attendance</h2>

               <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.permissions.update', $permission->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>User</label>
                    <select name="user_id" class="form-control" required>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ $permission->user_id == $u->id ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Tanggal Izin</label>
                    <input type="date" name="date_permission" class="form-control"
                           value="{{ $permission->date_permission }}" required>
                </div>

                <div class="form-group">
                    <label>Alasan</label>
                    <textarea name="reason" class="form-control" rows="4" required>{{ $permission->reason }}</textarea>
                </div>

                <div class="form-group">
                    <label>Bukti (Opsional)</label>
                    @if($permission->image)
                        <p><img src="{{ asset('storage/' . $permission->image) }}" width="80" class="rounded mb-2"></p>
                    @endif
                    <input type="file" name="image" class="form-control">
                </div>

                <div class="form-group">
                    <label>
                        <input type="hidden" name="is_approved" value="0">
                        <input type="checkbox" name="is_approved" value="1" {{ $permission->is_approved ? 'checked' : '' }}>
                        Disetujui?
                    </label>
                </div>

                <button class="btn btn-primary">Update</button>
                <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
@endpush
