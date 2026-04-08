@extends('layouts.app')

@section('title', 'Edit Payrolls ')

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
            <h1>Edit Payrolls</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                <div class="breadcrumb-item"><a href="#">Forms</a></div>
                <div class="breadcrumb-item">Payrolls</div>
            </div>
        </div>

        <div class="section-body">
            <h2 class="section-title">Edit Payrolls</h2>


               <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.payrools.update', $payroll->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>Pegawai</label>
                    <select name="user_id" class="form-control" required>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ $payroll->user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Periode Mulai</label>
                    <input type="date" name="period_start" class="form-control" value="{{ $payroll->period_start }}" required>
                </div>

                <div class="form-group">
                    <label>Periode Selesai</label>
                    <input type="date" name="period_end" class="form-control" value="{{ $payroll->period_end }}" required>
                </div>

                <div class="form-group">
                    <label>Gaji Pokok</label>
                    <input type="number" name="base_salary" class="form-control" value="{{ $payroll->base_salary }}" min="0">
                </div>

                <div class="form-group">
                    <label>Tunjangan</label>
                    <input type="number" name="allowance" class="form-control" value="{{ $payroll->allowance }}" min="0">
                </div>

                <div class="form-group">
                    <label>Potongan</label>
                    <input type="number" name="deductions" class="form-control" value="{{ $payroll->deductions }}" min="0">
                </div>

                <div class="form-group">
                    <label>Bayaran Lembur</label>
                    <input type="number" name="overtime_pay" class="form-control" value="{{ $payroll->overtime_pay }}" min="0">
                </div>

                <div class="form-group">
                    <label>Bonus</label>
                    <input type="number" name="bonus" class="form-control" value="{{ $payroll->bonus }}" min="0">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="draft" {{ $payroll->status == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="approved" {{ $payroll->status == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="paid" {{ $payroll->status == 'paid' ? 'selected' : '' }}>Paid</option>
                    </select>
                </div>

                <button class="btn btn-primary">Update</button>
                <a href="{{ route('admin.payrools.index') }}" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>

        </div>
    </section>
</div>
@endsection

@push('scripts')
@endpush
