@extends('layouts.app')

@section('title', 'Edit Draft Kebijakan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.app-policies.show', $policy->id) }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Draft Kebijakan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.app-policies.index') }}">Kebijakan Aplikasi</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.app-policies.update', $policy->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="card-header"><h4>{{ $policy->title }} — versi {{ $policy->version }}</h4></div>
                        <div class="card-body">
                            @include('pages.superadmin.app-policies._form', ['policy' => $policy])
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.app-policies.show', $policy->id) }}" class="btn btn-secondary">Batal</a>
                            <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
