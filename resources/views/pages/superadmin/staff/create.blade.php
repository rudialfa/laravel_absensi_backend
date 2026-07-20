@extends('layouts.app')

@section('title', 'Tambah Staff Superadmin')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Tambah Staff</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.staff.index') }}">Staff</a></div>
                    <div class="breadcrumb-item">Tambah</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Tambah Staff Superadmin</h2>

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.staff.store') }}">
                        @csrf

                        <div class="card-header">
                            <h4>Form Staff</h4>
                        </div>
                        <div class="card-body">

                            <div class="form-group">
                                <label>Nama</label>
                                <input type="text" name="name" value="{{ old('name') }}" class="form-control"
                                    required>
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" class="form-control"
                                    required>
                                @error('email')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Telepon</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                                @error('password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Konfirmasi Password</label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Simpan</button>
                            <a href="{{ route('superadmin.staff.index') }}" class="btn btn-secondary">Kembali</a>
                        </div>

                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
