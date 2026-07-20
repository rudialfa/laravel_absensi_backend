@extends('layouts.app')

@section('title', 'Edit Draft Kebijakan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Draft Kebijakan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.app-policies.index') }}">Kebijakan Aplikasi</a>
                    </div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Draft: {{ $policy->title }}</h2>

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.app-policies.update', $policy->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="card-header">
                            <h4>Form Kebijakan <small
                                    class="text-muted">({{ str_replace('_', ' ', ucfirst($policy->type)) }})</small></h4>
                        </div>
                        <div class="card-body">

                            <div class="form-group">
                                <label>Judul</label>
                                <input type="text" name="title" value="{{ old('title', $policy->title) }}"
                                    class="form-control" required>
                                @error('title')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Versi</label>
                                <input type="text" name="version" value="{{ old('version', $policy->version) }}"
                                    class="form-control" required>
                                @error('version')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Isi Kebijakan</label>
                                <textarea name="content" rows="12" class="form-control" required>{{ old('content', $policy->content) }}</textarea>
                                @error('content')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Update Draft</button>
                            <a href="{{ route('superadmin.app-policies.index') }}" class="btn btn-secondary">Back</a>
                        </div>

                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
