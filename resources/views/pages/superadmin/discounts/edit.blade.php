@extends('layouts.app')

@section('title', 'Edit Voucher')

@push('style')
    <link rel="stylesheet" href="{{ asset('backend/asset/library/select2/dist/css/select2.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Voucher</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.discounts.index') }}">Voucher</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Voucher: {{ $discount->name }}</h2>

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.discounts.update', $discount->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="card-header">
                            <h4>Form Voucher</h4>
                        </div>
                        <div class="card-body">
                            @include('superadmin.discounts._form')
                        </div>
                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Update</button>
                            <a href="{{ route('superadmin.discounts.index') }}" class="btn btn-secondary">Back</a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
