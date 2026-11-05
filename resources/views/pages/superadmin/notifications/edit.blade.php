@extends('layouts.app')

@section('title', 'Edit Pengumuman')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.notifications.show', $b->id) }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Pengumuman</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.notifications.index') }}">Kirim
                            Notifikasi</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <form method="POST" action="{{ route('superadmin.notifications.update', $b->id) }}">
                    @csrf
                    @method('PUT')
                    @include('pages.superadmin.notifications._form')
                </form>
            </div>
        </section>
    </div>
@endsection
