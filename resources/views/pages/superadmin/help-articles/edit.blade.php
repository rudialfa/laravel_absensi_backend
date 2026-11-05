@extends('layouts.app')

@section('title', 'Edit Artikel Bantuan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.help-articles.show', $article->id) }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Artikel Bantuan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.help-articles.index') }}">Bantuan / FAQ</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <form method="POST" action="{{ route('superadmin.help-articles.update', $article->id) }}">
                    @csrf
                    @method('PUT')
                    @include('pages.superadmin.help-articles._form')
                </form>
            </div>
        </section>
    </div>
@endsection
