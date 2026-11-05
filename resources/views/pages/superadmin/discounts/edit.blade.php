@extends('layouts.app')

@section('title', 'Edit Voucher')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.discounts.show', $discount->id) }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Voucher</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.discounts.index') }}">Voucher</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.discounts.show', $discount->id) }}">{{ $discount->name }}</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')
                @if ($inUse)
                    <div class="alert alert-light border"><i class="fas fa-info-circle"></i> Voucher ini sudah dipakai. Perubahan hanya berlaku untuk pemakaian berikutnya, invoice lama tidak berubah.</div>
                @endif

                <form method="POST" action="{{ route('superadmin.discounts.update', $discount->id) }}">
                    @csrf
                    @method('PUT')
                    @include('pages.superadmin.discounts._form')

                    <div class="card">
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.discounts.show', $discount->id) }}" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
