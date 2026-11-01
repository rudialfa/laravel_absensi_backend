@extends('layouts.app')

@section('title', 'Buat Kebijakan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Buat Versi Kebijakan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.app-policies.index') }}">Kebijakan Aplikasi</a></div>
                    <div class="breadcrumb-item">Buat</div>
                </div>
            </div>

            <div class="section-body">
                <div class="card">
                    <form method="POST" action="{{ route('superadmin.app-policies.store') }}">
                        @csrf
                        <div class="card-header">
                            <h4>Draft Baru</h4>
                        </div>
                        <div class="card-body">
                            @include('pages.superadmin.app-policies._form')
                        </div>
                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Simpan Draft</button>
                            <a href="{{ route('superadmin.app-policies.index') }}" class="btn btn-secondary">Kembali</a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection