@extends('layouts.app')

@section('title', 'Add Loans ')

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
            <h1>Add Loans</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                <div class="breadcrumb-item"><a href="#">Forms</a></div>
                <div class="breadcrumb-item">Loans</div>
            </div>
        </div>

        <div class="section-body">
            <h2 class="section-title">Add Loans</h2>


             <div class="card">
                <form action="{{ route('admin.loans.store') }}" method="POST">
                    @csrf

                    <div class="card-header">
                        <h4>Form Add Loan</h4>
                    </div>

                    <div class="card-body">

                        {{-- USER --}}
                        <div class="form-group">
                            <label>Pilih User</label>
                            <select name="user_id" class="form-control" required>
                                <option value="">-- Pilih User --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- AMOUNT --}}
                        <div class="form-group">
                            <label>Jumlah Kasbon</label>
                            <input type="number" name="amount" class="form-control"
                                   placeholder="Masukan jumlah kasbon" required min="0">
                        </div>

                        {{-- INSTALLMENT --}}
                        <div class="form-group">
                            <label>Durasi Cicilan (x kali)</label>
                            <input type="number" name="installments" class="form-control"
                                   required min="0" value="0">
                        </div>

                        {{-- STATUS --}}
                        <div class="form-group">
                            <label>Status Kasbon</label>
                            <select name="status" class="form-control" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>

                    </div>

                    <div class="card-footer text-right">
                        <button class="btn btn-primary">Submit</button>
                        <a href="{{ route('admin.loans.index') }}" class="btn btn-secondary">Kembali</a>
                    </div>

                </form>
            </div>

        </div>
    </section>
</div>
@endsection

@push('scripts')
@endpush
