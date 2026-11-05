@extends('layouts.app')

@section('title', 'Daftarkan Kiosk')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.devices.index') }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Daftarkan Kiosk</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.school.devices.index') }}">Perangkat
                            Kiosk</a></div>
                    <div class="breadcrumb-item">Daftarkan</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-7">
                        <form action="{{ route('superadmin.school.devices.store') }}" method="POST">
                            @csrf
                            <div class="card">
                                <div class="card-header">
                                    <h4>Data Kiosk</h4>
                                </div>
                                <div class="card-body">
                                    @include('pages.superadmin.school.devices._form')
                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('superadmin.school.devices.index') }}"
                                        class="btn btn-secondary">Batal</a>
                                    <button class="btn btn-primary"><i class="fas fa-save"></i> Daftarkan</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>Cara Pasang</h4>
                            </div>
                            <div class="card-body">
                                <ol class="pl-3 mb-0">
                                    <li class="mb-2">Simpan form ini. Token kiosk akan muncul <b>satu kali</b> di halaman
                                        berikutnya.</li>
                                    <li class="mb-2">Buka aplikasi kiosk di tablet/HP sekolah, lalu masukkan token
                                        tersebut.</li>
                                    <li class="mb-2">Kalau berhasil, status kiosk berubah menjadi <span
                                            class="badge badge-success">Online</span>.</li>
                                    <li>Kalau token hilang, buat token baru dari halaman detail kiosk.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
