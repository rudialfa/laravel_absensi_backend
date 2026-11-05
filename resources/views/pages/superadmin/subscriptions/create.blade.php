@extends('layouts.app')

@section('title', 'Buat Langganan')

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.subscriptions.index') }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Buat Langganan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.subscriptions.index') }}">Langganan</a></div>
                    <div class="breadcrumb-item">Buat</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Untuk tenant yang belum punya langganan sama sekali, misalnya pembayaran di luar aplikasi atau tenant
                    percobaan.
                    Langganan dibuat tanpa invoice.
                </p>

                <form method="POST" action="{{ route('superadmin.subscriptions.store') }}">
                    @csrf
                    <div class="card">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Tenant <span class="text-danger">*</span></label>
                                    <select name="company_id" class="form-control @error('company_id') is-invalid @enderror"
                                        required>
                                        <option value="">-- Pilih tenant tanpa langganan --</option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" @selected((string) old('company_id', $presetId) === (string) $company->id)>
                                                {{ $company->name }}
                                                ({{ $typeLabels[$company->type] ?? ucfirst($company->type) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('company_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Paket <span class="text-danger">*</span></label>
                                    <select name="plan_id" id="plan_id"
                                        class="form-control @error('plan_id') is-invalid @enderror" required>
                                        <option value="">-- Pilih paket --</option>
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}" data-days="{{ $plan->duration_days }}"
                                                data-free="{{ $plan->is_free ? 1 : 0 }}" @selected((string) old('plan_id') === (string) $plan->id)>
                                                {{ $plan->name }} —
                                                {{ $plan->is_free ? 'Gratis' : $rupiah($plan->price) }} /
                                                {{ $plan->duration_days }} hari
                                                {{ $plan->is_active ? '' : '(nonaktif)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Paket gratis → status <b>Trial</b>, paket berbayar → status
                                        <b>Aktif</b>.</small>
                                    @error('plan_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Tanggal Mulai <span class="text-danger">*</span></label>
                                    <input type="date" name="started_at" id="started_at"
                                        value="{{ old('started_at', now()->toDateString()) }}"
                                        class="form-control @error('started_at') is-invalid @enderror" required>
                                    @error('started_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Durasi (hari) <span class="text-danger">*</span></label>
                                    <input type="number" name="duration_days" id="duration_days" min="1"
                                        max="3650" value="{{ old('duration_days', 30) }}"
                                        class="form-control @error('duration_days') is-invalid @enderror" required>
                                    <small class="text-muted">Otomatis mengikuti paket, bisa diubah.</small>
                                    @error('duration_days')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Berakhir</label>
                                    <input type="text" id="expires_preview" class="form-control" readonly>
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <label>Alasan <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control @error('reason') is-invalid @enderror" rows="2" maxlength="500"
                                    required placeholder="Contoh: Bayar transfer manual ke rekening, bukti di email 3 Nov">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.subscriptions.index') }}" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Buat
                                Langganan</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const plan = document.getElementById('plan_id');
            const start = document.getElementById('started_at');
            const days = document.getElementById('duration_days');
            const preview = document.getElementById('expires_preview');

            function updatePreview() {
                const d = new Date(start.value + 'T00:00:00');
                const n = parseInt(days.value) || 0;
                if (isNaN(d) || !n) {
                    preview.value = '-';
                    return;
                }
                d.setDate(d.getDate() + n);
                preview.value = d.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
            }

            plan.addEventListener('change', () => {
                const opt = plan.options[plan.selectedIndex];
                if (opt && opt.dataset.days) days.value = opt.dataset.days;
                updatePreview();
            });
            [start, days].forEach(el => el.addEventListener('input', updatePreview));
            updatePreview();
        })();
    </script>
@endpush
