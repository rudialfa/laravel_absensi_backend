{{--
    Form voucher — dipakai create & edit.
    Variabel: $plans, $discount (edit), $template (create salinan), $inUse (edit)
--}}
@php
    $d = $discount ?? ($template ?? null);
    $isEdit = isset($discount);
    $fmtDate = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '';
    $type = old('discount_type', $d->discount_type ?? 'percent');
@endphp

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <h4>Voucher</h4>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nama Voucher <span class="text-danger">*</span></label>
                        <input type="text" name="name"
                            value="{{ old('name', $isEdit ? $d->name : ($d ? $d->name . ' (salinan)' : '')) }}"
                            class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Promo Ramadan"
                            required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Kode Voucher</label>
                        <div class="input-group">
                            <input type="text" name="code" id="code"
                                value="{{ old('code', $isEdit ? $d->code : '') }}"
                                class="form-control @error('code') is-invalid @enderror"
                                style="text-transform:uppercase" placeholder="RAMADAN2026" maxlength="50">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-primary" id="gen-code"
                                    title="Buat kode acak"><i class="fas fa-random"></i></button>
                            </div>
                        </div>
                        <small class="text-muted">Kosongkan untuk <b>diskon otomatis</b> (langsung berlaku tanpa
                            kode).</small>
                        @error('code')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Tipe Diskon <span class="text-danger">*</span></label>
                        <select name="discount_type" id="discount_type" class="form-control" required>
                            <option value="percent" @selected($type === 'percent')>Persen (%)</option>
                            <option value="fixed" @selected($type === 'fixed')>Nominal (Rp)</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Nilai Diskon <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend js-fixed"><span class="input-group-text">Rp</span></div>
                            <input type="number" step="any" min="0" name="discount_value"
                                id="discount_value"
                                value="{{ old('discount_value', isset($d->discount_value) ? (float) $d->discount_value : '') }}"
                                class="form-control @error('discount_value') is-invalid @enderror" required>
                            <div class="input-group-append js-percent"><span class="input-group-text">%</span></div>
                        </div>
                        @error('discount_value')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-4 js-percent">
                        <label>Maks Potongan (Rp)</label>
                        <input type="number" step="1" min="1" name="max_discount_amount"
                            value="{{ old('max_discount_amount', isset($d->max_discount_amount) ? (int) $d->max_discount_amount : '') }}"
                            class="form-control @error('max_discount_amount') is-invalid @enderror"
                            placeholder="Tanpa batas">
                        @error('max_discount_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label>Berlaku untuk Paket</label>
                    <select name="plan_id" id="plan_id" class="form-control @error('plan_id') is-invalid @enderror">
                        <option value="">Semua paket berbayar</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" data-price="{{ (float) $plan->price }}"
                                @selected((string) old('plan_id', $d->plan_id ?? '') === (string) $plan->id)>
                                {{ $plan->name }} —
                                {{ $plan->is_free ? 'Gratis' : 'Rp ' . number_format($plan->price, 0, ',', '.') }}
                                {{ $plan->is_active ? '' : '(nonaktif)' }}
                            </option>
                        @endforeach
                    </select>
                    @error('plan_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="alert alert-light border small mt-2 mb-0" id="simulation" style="display:none"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <h4>Batasan &amp; Status</h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Berlaku Dari</label>
                    <input type="date" name="valid_from"
                        value="{{ old('valid_from', $isEdit ? $fmtDate($d->valid_from) : '') }}"
                        class="form-control @error('valid_from') is-invalid @enderror">
                    <small class="text-muted">Kosong = langsung berlaku</small>
                    @error('valid_from')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label>Berlaku Sampai</label>
                    <input type="date" name="valid_until"
                        value="{{ old('valid_until', $isEdit ? $fmtDate($d->valid_until) : '') }}"
                        class="form-control @error('valid_until') is-invalid @enderror">
                    <small class="text-muted">Berlaku sampai pukul 23:59. Kosong = tanpa batas.</small>
                    @error('valid_until')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label>Batas Pemakaian</label>
                    <input type="number" min="1" name="max_usage"
                        value="{{ old('max_usage', $d->max_usage ?? '') }}"
                        class="form-control @error('max_usage') is-invalid @enderror" placeholder="Tanpa batas">
                    @if ($isEdit)
                        <small class="text-muted">Sudah dipakai {{ $d->used_count }} kali.</small>
                    @endif
                    @error('max_usage')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group mb-0">
                    <label class="custom-switch pl-0">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="custom-switch-input"
                            @checked(old('is_active', $isEdit ? $d->is_active : true))>
                        <span class="custom-switch-indicator"></span>
                        <span class="custom-switch-description">Voucher aktif</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function() {
            const type = document.getElementById('discount_type');
            const value = document.getElementById('discount_value');
            const plan = document.getElementById('plan_id');
            const maxInput = document.querySelector('[name="max_discount_amount"]');
            const sim = document.getElementById('simulation');
            const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');

            function sync() {
                const isPercent = type.value === 'percent';
                document.querySelectorAll('.js-percent').forEach(el => el.style.display = isPercent ? '' : 'none');
                document.querySelectorAll('.js-fixed').forEach(el => el.style.display = isPercent ? 'none' : '');
                value.max = isPercent ? 100 : '';
                simulate();
            }

            // Simulasi potongan untuk paket yang dipilih
            function simulate() {
                const opt = plan.options[plan.selectedIndex];
                const price = opt && opt.dataset.price ? parseFloat(opt.dataset.price) : 0;
                const v = parseFloat(value.value) || 0;
                if (!price || !v) {
                    sim.style.display = 'none';
                    return;
                }

                let cut = type.value === 'percent' ? price * v / 100 : v;
                const max = parseFloat(maxInput.value) || 0;
                if (type.value === 'percent' && max > 0) cut = Math.min(cut, max);
                cut = Math.min(cut, price);

                sim.innerHTML = 'Simulasi: ' + rp(price) + ' − <b>' + rp(cut) + '</b> = <b>' + rp(price - cut) + '</b>';
                sim.style.display = '';
            }

            document.getElementById('gen-code').addEventListener('click', () => {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                let code = '';
                for (let i = 0; i < 8; i++) code += chars[Math.floor(Math.random() * chars.length)];
                document.getElementById('code').value = code;
            });

            [type, plan].forEach(el => el.addEventListener('change', sync));
            [value, maxInput].forEach(el => el.addEventListener('input', simulate));
            sync();
        })();
    </script>
@endpush
