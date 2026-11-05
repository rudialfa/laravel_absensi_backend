@extends('layouts.app')

@section('title', 'Buat Invoice')

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $discountData = $discounts
            ->map(
                fn($d) => [
                    'id' => $d->id,
                    'type' => $d->discount_type,
                    'value' => (float) $d->discount_value,
                    'max' => $d->max_discount_amount ? (float) $d->max_discount_amount : null,
                    'plan_id' => $d->plan_id,
                ],
            )
            ->keyBy('id');
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.invoices.index') }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Buat Invoice</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.invoices.index') }}">Invoice</a></div>
                    <div class="breadcrumb-item">Buat</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Tagihan langganan yang dibuat dari panel, misalnya untuk transfer manual atau penawaran khusus.
                    Perhitungannya sama dengan saat tenant memilih paket di aplikasi.
                </p>

                <form method="POST" action="{{ route('superadmin.invoices.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-lg-8">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Tenant <span class="text-danger">*</span></label>
                                        <select name="company_id"
                                            class="form-control @error('company_id') is-invalid @enderror" required>
                                            <option value="">-- Pilih tenant --</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company->id }}" @selected((string) old('company_id', $presetId) === (string) $company->id)>
                                                    {{ $company->name }}{{ $company->is_active ? '' : ' (disuspend)' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('company_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Paket <span class="text-danger">*</span></label>
                                            <select name="plan_id" id="plan_id"
                                                class="form-control @error('plan_id') is-invalid @enderror" required>
                                                <option value="">-- Pilih paket --</option>
                                                @foreach ($plans as $plan)
                                                    <option value="{{ $plan->id }}"
                                                        data-price="{{ (float) $plan->price }}"
                                                        @selected((string) old('plan_id') === (string) $plan->id)>
                                                        {{ $plan->name }} — {{ $rupiah($plan->price) }} /
                                                        {{ $plan->duration_days }}
                                                        hari{{ $plan->is_active ? '' : ' (nonaktif)' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('plan_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Voucher</label>
                                            <select name="discount_id" id="discount_id"
                                                class="form-control @error('discount_id') is-invalid @enderror">
                                                <option value="">Tanpa voucher</option>
                                                @foreach ($discounts as $discount)
                                                    <option value="{{ $discount->id }}" @selected((string) old('discount_id') === (string) $discount->id)>
                                                        {{ $discount->code ?? 'Otomatis' }} · {{ $discount->name }}
                                                        ({{ $discount->discount_type === 'percent' ? (float) $discount->discount_value . '%' : $rupiah($discount->discount_value) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Hanya voucher yang sedang berlaku.</small>
                                            @error('discount_id')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Batas bayar <span class="text-danger">*</span></label>
                                            <select name="due_hours" class="form-control">
                                                @foreach ([24 => '24 jam (sama seperti aplikasi)', 72 => '3 hari', 168 => '7 hari', 336 => '14 hari', 720 => '30 hari'] as $h => $label)
                                                    <option value="{{ $h }}" @selected((int) old('due_hours', 24) === $h)>
                                                        {{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0">
                                        <label>Catatan</label>
                                        <textarea name="notes" class="form-control" rows="2" maxlength="1000"
                                            placeholder="Contoh: Penawaran khusus perpanjangan tahunan">{{ old('notes') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Ringkasan</h4>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between"><span>Subtotal</span><span
                                            id="sum-subtotal">-</span></div>
                                    <div class="d-flex justify-content-between text-danger"><span>Diskon</span><span
                                            id="sum-discount">-</span></div>
                                    <hr>
                                    <div class="d-flex justify-content-between h5 mb-0"><span>Total</span><span
                                            id="sum-total">-</span></div>
                                    <small class="text-danger d-none" id="sum-warning">Voucher ini khusus paket
                                        lain.</small>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h4>Virtual Account</h4>
                                </div>
                                <div class="card-body">
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="hidden" name="create_va" value="0">
                                        <input type="checkbox" name="create_va" value="1" class="custom-control-input"
                                            id="create_va" @checked(old('create_va'))>
                                        <label class="custom-control-label" for="create_va">Langsung buat VA
                                            Midtrans</label>
                                    </div>
                                    <select name="bank" id="bank"
                                        class="form-control @error('bank') is-invalid @enderror">
                                        @foreach ($banks as $value => $label)
                                            <option value="{{ $value }}" @selected(old('bank', 'bca') === $value)>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('bank')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted d-block mt-1">Kosongkan centang untuk pembayaran transfer
                                        manual. VA bisa dibuat nanti.</small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="fas fa-save"></i> Buat
                                Invoice</button>
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
            const discounts = @json($discountData);
            const plan = document.getElementById('plan_id');
            const discount = document.getElementById('discount_id');
            const createVa = document.getElementById('create_va');
            const bank = document.getElementById('bank');
            const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');

            // Rumus sama dengan SubscriptionDiscount::calculateDiscount()
            function calc() {
                const opt = plan.options[plan.selectedIndex];
                const price = opt && opt.dataset.price ? parseFloat(opt.dataset.price) : 0;
                const d = discounts[discount.value];
                let cut = 0;
                let mismatch = false;

                if (d && price) {
                    if (d.plan_id && String(d.plan_id) !== plan.value) {
                        mismatch = true;
                    } else if (d.type === 'percent') {
                        cut = price * d.value / 100;
                        if (d.max) cut = Math.min(cut, d.max);
                    } else {
                        cut = Math.min(d.value, price);
                    }
                }

                document.getElementById('sum-subtotal').textContent = price ? rp(price) : '-';
                document.getElementById('sum-discount').textContent = cut ? '−' + rp(cut) : '-';
                document.getElementById('sum-total').textContent = price ? rp(Math.max(0, price - cut)) : '-';
                document.getElementById('sum-warning').classList.toggle('d-none', !mismatch);
            }

            function syncVa() {
                bank.disabled = !createVa.checked;
            }

            [plan, discount].forEach(el => el.addEventListener('change', calc));
            createVa.addEventListener('change', syncVa);
            calc();
            syncVa();
        })();
    </script>
@endpush
