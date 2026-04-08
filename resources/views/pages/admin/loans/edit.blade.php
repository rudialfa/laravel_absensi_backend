@extends('layouts.app')

@section('title', 'Edit Loans ')

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
            <h1>Edit Loans</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                <div class="breadcrumb-item"><a href="#">Forms</a></div>
                <div class="breadcrumb-item">Loans</div>
            </div>
        </div>

        <div class="section-body">
            <h2 class="section-title">Edit Loans</h2>


          <div class="card">
                <form action="{{ route('admin.loans.update', $loan->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="card-header">
                        <h4>Form Edit Loan</h4>
                    </div>

                    <div class="card-body">

                        {{-- USER --}}
                        <div class="form-group">
                            <label>Pilih User</label>
                            <select name="user_id" class="form-control" required>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}"
                                        {{ $loan->user_id == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- AMOUNT --}}
                        <div class="form-group">
                            <label>Total Kasbon</label>
                            <input type="number" name="amount" class="form-control"
                                   value="{{ $loan->amount }}" required>
                        </div>

                        {{-- BALANCE --}}
                        <div class="form-group">
                            <label>Sisa Kasbon (Balance)</label>
                            <input type="number" name="balance" class="form-control"
                                   value="{{ $loan->balance }}" required>
                        </div>

                        {{-- INSTALLMENTS --}}
                        <div class="form-group">
                            <label>Durasi Cicilan (x kali)</label>
                            <input type="number" name="installments" class="form-control"
                                   value="{{ $loan->installments }}" required>
                        </div>

                        {{-- STATUS --}}
                        <div class="form-group">
                            <label>Status Kasbon</label>
                            <select name="status" class="form-control" required>
                                <option value="pending"   {{ $loan->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved"  {{ $loan->status == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected"  {{ $loan->status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="paid"      {{ $loan->status == 'paid' ? 'selected' : '' }}>Paid</option>
                            </select>
                        </div>

                    </div>

                    <div class="card-footer text-right">
                        <button class="btn btn-primary">Update</button>
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
