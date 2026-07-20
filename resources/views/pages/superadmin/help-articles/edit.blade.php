@extends('layouts.app')

@section('title', 'Edit Artikel Bantuan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Artikel Bantuan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Bantuan Aplikasi</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Artikel Bantuan</h2>

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.help-articles.update', $article->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="card-header">
                            <h4>Form Artikel Bantuan</h4>
                        </div>
                        <div class="card-body">

                            <div class="form-group">
                                <label>Kategori</label>
                                <input type="text" name="category" value="{{ old('category', $article->category) }}"
                                    class="form-control" required>
                                @error('category')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Judul / Pertanyaan</label>
                                <input type="text" name="title" value="{{ old('title', $article->title) }}"
                                    class="form-control" required>
                                @error('title')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Isi Jawaban</label>
                                <textarea name="content" rows="8" class="form-control" required>{{ old('content', $article->content) }}</textarea>
                                @error('content')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Urutan Tampil</label>
                                <input type="number" name="sort_order"
                                    value="{{ old('sort_order', $article->sort_order) }}" class="form-control"
                                    min="0">
                            </div>

                            <div class="form-group">
                                <div class="custom-checkbox custom-control">
                                    <input type="checkbox" name="is_published" value="1" class="custom-control-input"
                                        id="is_published" @checked(old('is_published', $article->is_published))>
                                    <label for="is_published" class="custom-control-label">Publish</label>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Update</button>
                            <a href="{{ route('superadmin.help-articles.index') }}" class="btn btn-secondary">Back</a>
                        </div>

                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
