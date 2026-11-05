@extends('layouts.app')

@section('title', 'Tambah Tenant')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Tambah Tenant</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.tenants.index') }}">Tenant</a></div>
                    <div class="breadcrumb-item">Tambah</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <form method="POST" action="{{ route('superadmin.tenants.store') }}" enctype="multipart/form-data">
                    @csrf

                    @include('pages.superadmin.tenants._form')

                    <div class="card">
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan
                                Tenant</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
