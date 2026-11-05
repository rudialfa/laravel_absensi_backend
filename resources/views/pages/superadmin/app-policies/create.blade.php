@extends('layouts.app')

@section('title', 'Buat Versi Kebijakan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ $source ? route('superadmin.app-policies.show', $source->id) : route('superadmin.app-policies.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Buat Versi Kebijakan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.app-policies.index') }}">Kebijakan Aplikasi</a></div>
                    <div class="breadcrumb-item">Versi Baru</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($source)
                    <div class="alert alert-light border">
                        <i class="fas fa-code-branch"></i> Disalin dari <b>{{ $typeLabels[$source->type] ?? $source->type }} versi {{ $source->version }}</b>.
                        Ubah bagian yang perlu, lalu simpan sebagai draft. Versi aktif tidak berubah sampai draft ini di-Publish.
                    </div>
                @endif

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.app-policies.store') }}">
                        @csrf
                        <div class="card-header"><h4>Draft Baru</h4></div>
                        <div class="card-body">
                            @include('pages.superadmin.app-policies._form')
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.app-policies.index') }}" class="btn btn-secondary">Batal</a>
                            <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Draft</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
