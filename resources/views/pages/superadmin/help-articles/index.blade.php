@extends('layouts.app')

@section('title', 'Bantuan Aplikasi')

@push('style')
    <link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Bantuan Aplikasi</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.help-articles.create') }}" class="btn btn-primary">Add New</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Superadmin</a></div>
                    <div class="breadcrumb-item">Bantuan Aplikasi</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>
                <h2 class="section-title">Artikel Bantuan / FAQ</h2>
                <p class="section-lead">
                    Kelola artikel bantuan yang tampil ke semua tenant (HR, ustadz, employee, santri, dst).
                </p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Semua Artikel</h4>
                            </div>
                            <div class="card-body">

                                <div class="float-right">
                                    <form method="GET" action="{{ route('superadmin.help-articles.index') }}"
                                        class="form-inline">
                                        <select name="category" class="form-control mr-2">
                                            <option value="">Semua Kategori</option>
                                            @foreach ($categories as $cat)
                                                <option value="{{ $cat }}" @selected(request('category') === $cat)>
                                                    {{ $cat }}</option>
                                            @endforeach
                                        </select>
                                        <select name="status" class="form-control mr-2">
                                            <option value="">Semua Status</option>
                                            <option value="published" @selected(request('status') === 'published')>Published</option>
                                            <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                        </select>
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari judul"
                                                name="search" value="{{ request('search') }}">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="clearfix mb-3"></div>

                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>Kategori</th>
                                                <th>Judul</th>
                                                <th>Dilihat</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($articles as $article)
                                                <tr>
                                                    <td>{{ $article->category }}</td>
                                                    <td>{{ $article->title }}</td>
                                                    <td>{{ $article->view_count }}</td>
                                                    <td>
                                                        <span
                                                            class="badge badge-{{ $article->is_published ? 'success' : 'secondary' }}">
                                                            {{ $article->is_published ? 'Published' : 'Draft' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            <a href="{{ route('superadmin.help-articles.edit', $article->id) }}"
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>

                                                            <form
                                                                action="{{ route('superadmin.help-articles.toggle-publish', $article->id) }}"
                                                                method="POST" class="ml-2">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button class="btn btn-sm btn-warning btn-icon">
                                                                    <i class="fas fa-eye"></i>
                                                                    {{ $article->is_published ? 'Unpublish' : 'Publish' }}
                                                                </button>
                                                            </form>

                                                            <form
                                                                action="{{ route('superadmin.help-articles.destroy', $article->id) }}"
                                                                method="POST" class="ml-2">
                                                                <input type="hidden" name="_method" value="DELETE" />
                                                                <input type="hidden" name="_token"
                                                                    value="{{ csrf_token() }}" />
                                                                <button
                                                                    class="btn btn-sm btn-danger btn-icon confirm-delete">
                                                                    <i class="fas fa-times"></i> Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">Belum ada artikel bantuan</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $articles->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('backend/asset/library/selectric/public/jquery.selectric.min.js') }}"></script>
@endpush
