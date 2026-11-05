@extends('layouts.app')

@section('title', 'Buat Pengumuman')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.notifications.index') }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Buat Pengumuman</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.notifications.index') }}">Kirim
                            Notifikasi</a></div>
                    <div class="breadcrumb-item">Buat</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')
                @unless ($fcmConfigured)
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> FCM belum dikonfigurasi:
                        pengumuman hanya masuk kotak masuk aplikasi, tanpa pop-up di HP.</div>
                @endunless

                <form method="POST" action="{{ route('superadmin.notifications.store') }}">
                    @csrf
                    @include('pages.superadmin.notifications._form')
                </form>
            </div>
        </section>
    </div>
@endsection
