@extends('layouts.app')

@section('title', 'Tambah Staff')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.staff.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Tambah Staff</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.staff.index') }}">Staff</a></div>
                    <div class="breadcrumb-item">Tambah</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')
                <p class="section-lead">Staff superadmin punya akses <strong>penuh</strong> ke seluruh panel ini, termasuk menghapus tenant. Berikan hanya ke orang yang dipercaya.</p>

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.staff.store') }}">
                        @csrf

                        <div class="card-header">
                            <h4>Data Staff</h4>
                        </div>

                        @include('pages.superadmin.staff._form')

                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.staff.index') }}" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
