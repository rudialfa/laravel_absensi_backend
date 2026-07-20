@extends('layouts.app')

@section('title', 'Tambah Artikel Bantuan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Tambah Artikel Bantuan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Bantuan Aplikasi</a></div>
                    <div class="breadcrumb-item">Tambah</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Tambah Artikel Bantuan</h2>

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.help-articles.store') }}">
                        @csrf

                        <div class="card-header">
                            <h4>Form Artikel Bantuan</h4>
                        </div>
                        <div class="card-body">

                            <div class="form-group">
                                <label>Kategori</label>
                                <input type="text" name="category" value="{{ old('category') }}" class="form-control"
                                    placeholder="Contoh: Absensi, Pembayaran, Akun" required>
                                @error('category')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Judul / Pertanyaan</label>
                                <input type="text" name="title" value="{{ old('title') }}" class="form-control"
                                    required>
                                @error('title')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Isi Jawaban</label>
                                <textarea name="content" rows="8" class="form-control" required>{{ old('content') }}</textarea>
                                @error('content')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Urutan Tampil</label>
                                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}"
                                    class="form-control" min="0">
                            </div>

                            <div class="form-group">
                                <div class="custom-checkbox custom-control">
                                    <input type="checkbox" name="is_published" value="1" class="custom-control-input"
                                        id="is_published" checked>
                                    <label for="is_published" class="custom-control-label">Publish sekarang</label>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Save</button>
                            <a href="{{ route('superadmin.help-articles.index') }}" class="btn btn-secondary">Back</a>
                        </div>

                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
