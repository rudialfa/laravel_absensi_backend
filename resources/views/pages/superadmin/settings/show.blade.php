@extends('layouts.app')

@section('title', 'Pengaturan Sistem')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Pengaturan Sistem</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Settings</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Pengaturan Umum</h2>

                <div class="card">
                    <form method="POST" action="{{ route('superadmin.settings.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="card-header">
                            <h4>Konfigurasi Platform</h4>
                        </div>
                        <div class="card-body">

                            <div class="form-group">
                                <label>Nama Aplikasi</label>
                                <input type="text" name="app_name" value="{{ old('app_name', $settings['app_name']) }}"
                                    class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Email Support</label>
                                <input type="email" name="support_email"
                                    value="{{ old('support_email', $settings['support_email']) }}" class="form-control">
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Durasi Trial (hari)</label>
                                    <input type="number" name="trial_duration_days"
                                        value="{{ old('trial_duration_days', $settings['trial_duration_days'] ?? 7) }}"
                                        class="form-control" min="0">
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Masa Grace Period (hari)</label>
                                    <input type="number" name="grace_period_days"
                                        value="{{ old('grace_period_days', $settings['grace_period_days'] ?? 3) }}"
                                        class="form-control" min="0">
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-checkbox custom-control">
                                    <input type="checkbox" name="maintenance_mode" value="1"
                                        class="custom-control-input" id="maintenance_mode" @checked(old('maintenance_mode', $settings['maintenance_mode']))>
                                    <label class="custom-control-label" for="maintenance_mode">Aktifkan Mode
                                        Maintenance</label>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Simpan Pengaturan</button>
                        </div>

                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
