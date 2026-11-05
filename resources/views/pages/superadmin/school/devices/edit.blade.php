@extends('layouts.app')

@section('title', 'Edit Kiosk ' . $device->name)

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.devices.show', $device->id) }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Kiosk</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.school.devices.index') }}">Perangkat
                            Kiosk</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.school.devices.show', $device->id) }}">{{ $device->name }}</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-7">
                        <form action="{{ route('superadmin.school.devices.update', $device->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="card">
                                <div class="card-header">
                                    <h4>Data Kiosk</h4>
                                </div>
                                <div class="card-body">
                                    @include('pages.superadmin.school.devices._form')
                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('superadmin.school.devices.show', $device->id) }}"
                                        class="btn btn-secondary">Batal</a>
                                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted mb-2">Token tidak bisa dilihat atau diubah dari form ini.</p>
                                <p class="text-muted mb-0">Untuk perangkat yang hilang atau diganti, gunakan <b>Buat Token
                                        Baru</b> di halaman detail kiosk.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
