@extends('layouts.app')

@section('title', 'Edit Pengguna')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.users.show', $user->id) }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Pengguna</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.users.index') }}">Pengguna</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.users.show', $user->id) }}">{{ $user->name }}</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <form method="POST" action="{{ route('superadmin.users.update', $user->id) }}"
                    enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    @include('pages.superadmin.users._form')

                    <div class="card">
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.users.show', $user->id) }}" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan
                                Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
