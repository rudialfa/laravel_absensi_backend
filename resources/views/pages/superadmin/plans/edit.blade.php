@extends('layouts.app')

@section('title', 'Edit Paket Langganan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Paket Langganan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.plans.index') }}">Plans</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Paket</h2>

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.plans.update', $plan->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="card-header">
                            <h4>Form Paket Langganan</h4>
                        </div>
                        <div class="card-body">

                            <div class="form-group">
                                <label>Nama Paket</label>
                                <input type="text" name="name" value="{{ old('name', $plan->name) }}"
                                    class="form-control" required>
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Slug</label>
                                <input type="text" name="slug" value="{{ old('slug', $plan->slug) }}"
                                    class="form-control" required>
                                @error('slug')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Deskripsi</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $plan->description) }}</textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Durasi (hari)</label>
                                    <input type="number" name="duration_days"
                                        value="{{ old('duration_days', $plan->duration_days) }}" class="form-control"
                                        min="1" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Harga (Rp)</label>
                                    <input type="number" step="0.01" name="price"
                                        value="{{ old('price', $plan->price) }}" class="form-control" min="0"
                                        required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Urutan Tampil</label>
                                    <input type="number" name="sort_order"
                                        value="{{ old('sort_order', $plan->sort_order) }}" class="form-control"
                                        min="0">
                                </div>
                                <div class="form-group col-md-4">
                                    <div class="custom-checkbox custom-control mt-4">
                                        <input type="checkbox" name="is_free" value="1" class="custom-control-input"
                                            id="is_free" @checked(old('is_free', $plan->is_free))>
                                        <label class="custom-control-label" for="is_free">Paket Gratis</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <div class="custom-checkbox custom-control mt-4">
                                        <input type="checkbox" name="is_active" value="1" class="custom-control-input"
                                            id="is_active" @checked(old('is_active', $plan->is_active))>
                                        <label class="custom-control-label" for="is_active">Aktif</label>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Update</button>
                            <a href="{{ route('superadmin.plans.index') }}" class="btn btn-secondary">Kembali</a>
                        </div>

                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
