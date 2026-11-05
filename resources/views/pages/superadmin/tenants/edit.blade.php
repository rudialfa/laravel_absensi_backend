@extends('layouts.app')

@section('title', 'Edit Tenant')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.tenants.show', $tenant->id) }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Tenant</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.tenants.index') }}">Tenant</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.tenants.show', $tenant->id) }}">{{ $tenant->name }}</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <form method="POST" action="{{ route('superadmin.tenants.update', $tenant->id) }}"
                    enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    @include('pages.superadmin.tenants._form')

                    <div class="card">
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.tenants.show', $tenant->id) }}"
                                class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan
                                Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
