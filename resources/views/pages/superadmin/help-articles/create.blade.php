@extends('layouts.app')

@section('title', 'Tambah Artikel Bantuan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.help-articles.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Tambah Artikel Bantuan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.help-articles.index') }}">Bantuan / FAQ</a></div>
                    <div class="breadcrumb-item">Tambah</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')
                @if (request('copy'))
                    <div class="alert alert-light border"><i class="fas fa-copy"></i> Salinan dari artikel lain. Disimpan sebagai draft sampai Anda menayangkannya.</div>
                @endif

                <form method="POST" action="{{ route('superadmin.help-articles.store') }}">
                    @csrf
                    @include('pages.superadmin.help-articles._form')
                </form>
            </div>
        </section>
    </div>
@endsection
