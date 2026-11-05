@extends('layouts.app')

@section('title', 'Tambah Pengguna')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ url()->previous() === url()->current() ? route('superadmin.users.index') : url()->previous() }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Tambah Pengguna</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.users.index') }}">Pengguna</a></div>
                    <div class="breadcrumb-item">Tambah</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <form method="POST" action="{{ route('superadmin.users.store') }}" enctype="multipart/form-data">
                    @csrf

                    @include('pages.superadmin.users._form')

                    <div class="card">
                        <div class="card-footer text-right">
                            <a href="{{ url()->previous() === url()->current() ? route('superadmin.users.index') : url()->previous() }}"
                                class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan
                                Pengguna</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
