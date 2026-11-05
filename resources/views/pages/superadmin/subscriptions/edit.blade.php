@extends('layouts.app')

@section('title', 'Edit Langganan')

@section('main')
    @php
        $s = $subscription;
        $fmt = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.subscriptions.show', $s->id) }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Langganan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.subscriptions.index') }}">Langganan</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.subscriptions.show', $s->id) }}">{{ $s->company->name ?? '-' }}</a>
                    </div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Halaman ini mengubah data langganan <strong>{{ $s->company->name ?? '' }}</strong> secara langsung,
                    tanpa invoice.
                    Untuk kasus biasa, lebih aman memakai tombol <b>Perpanjang</b> atau <b>Ganti Paket</b> di halaman
                    detail.
                </div>

                <form method="POST" action="{{ route('superadmin.subscriptions.update', $s->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="card">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Paket <span class="text-danger">*</span></label>
                                    <select name="plan_id" class="form-control @error('plan_id') is-invalid @enderror"
                                        required>
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}" @selected((string) old('plan_id', $s->plan_id) === (string) $plan->id)>
                                                {{ $plan->name }} ({{ $plan->duration_days }}
                                                hari){{ $plan->is_active ? '' : ' — nonaktif' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('plan_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-control @error('status') is-invalid @enderror"
                                        required>
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected(old('status', $s->status) === $value)>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Trial/Aktif dengan tanggal berakhir yang sudah lewat otomatis
                                        menjadi Toleransi/Kedaluwarsa.</small>
                                    @error('status')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Mulai <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="started_at"
                                        value="{{ old('started_at', $fmt($s->started_at)) }}"
                                        class="form-control @error('started_at') is-invalid @enderror" required>
                                    @error('started_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Berakhir <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="expires_at"
                                        value="{{ old('expires_at', $fmt($s->expires_at)) }}"
                                        class="form-control @error('expires_at') is-invalid @enderror" required>
                                    <small class="text-muted">Setelah tanggal ini ada toleransi
                                        {{ \App\Models\CompanySubscription::graceDays() }} hari sebelum
                                        terkunci.</small>
                                    @error('expires_at')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="hidden" name="has_used_trial" value="0">
                                    <input type="checkbox" name="has_used_trial" value="1" class="custom-control-input"
                                        id="has_used_trial" @checked(old('has_used_trial', $s->has_used_trial))>
                                    <label class="custom-control-label" for="has_used_trial">Sudah pernah memakai
                                        trial</label>
                                </div>
                                <small class="text-muted">Hilangkan centang untuk mengizinkan tenant memulai trial lagi dari
                                    aplikasi.</small>
                            </div>

                            <div class="form-group mb-0">
                                <label>Alasan perubahan <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control @error('reason') is-invalid @enderror" rows="2" maxlength="500"
                                    required>{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.subscriptions.show', $s->id) }}"
                                class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan
                                Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
