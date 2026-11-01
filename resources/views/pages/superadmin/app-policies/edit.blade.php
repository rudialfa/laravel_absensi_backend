@extends('layouts.app')

@section('title', 'Edit Kebijakan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Draft Kebijakan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.app-policies.index') }}">Kebijakan Aplikasi</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                <div class="card">
                    <form method="POST" action="{{ route('superadmin.app-policies.update', $policy->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="card-header">
                            <h4>{{ $policy->title }} — versi {{ $policy->version }}</h4>
                        </div>
                        <div class="card-body">
                            @include('pages.superadmin.app-policies._form', ['policy' => $policy])
                        </div>
                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Simpan Perubahan</button>
                            <a href="{{ route('superadmin.app-policies.index') }}" class="btn btn-secondary">Kembali</a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection