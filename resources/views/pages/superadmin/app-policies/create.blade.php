@extends('layouts.app')

@section('title', 'Buat Kebijakan Baru')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Buat Kebijakan Baru</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.app-policies.index') }}">Kebijakan Aplikasi</a>
                    </div>
                    <div class="breadcrumb-item">Tambah</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Buat Draft Kebijakan</h2>
                <p class="section-lead">Draft ini belum tayang ke tenant sampai Anda klik "Publish" dari halaman daftar.</p>

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.app-policies.store') }}">
                        @csrf

                        <div class="card-header">
                            <h4>Form Kebijakan</h4>
                        </div>
                        <div class="card-body">

                            <div class="form-group">
                                <label>Tipe Kebijakan</label>
                                <select name="type" class="form-control" required>
                                    <option value="privacy_policy" @selected(old('type') === 'privacy_policy')>Privacy Policy</option>
                                    <option value="terms_of_service" @selected(old('type') === 'terms_of_service')>Terms of Service</option>
                                    <option value="refund_policy" @selected(old('type') === 'refund_policy')>Refund Policy</option>
                                    <option value="other" @selected(old('type') === 'other')>Lainnya</option>
                                </select>
                                @error('type')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Judul</label>
                                <input type="text" name="title" value="{{ old('title') }}" class="form-control"
                                    required>
                                @error('title')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Versi</label>
                                <input type="text" name="version" value="{{ old('version', '1.0') }}"
                                    class="form-control" required>
                                @error('version')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Isi Kebijakan</label>
                                <textarea name="content" rows="12" class="form-control" required>{{ old('content') }}</textarea>
                                @error('content')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Simpan Draft</button>
                            <a href="{{ route('superadmin.app-policies.index') }}" class="btn btn-secondary">Back</a>
                        </div>

                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
