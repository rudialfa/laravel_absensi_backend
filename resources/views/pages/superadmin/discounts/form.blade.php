@php $d = $discount ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nama Voucher</label>
        <input type="text" name="name" value="{{ old('name', $d->name ?? '') }}" class="form-control" required>
        @error('name')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Kode Voucher <small class="text-muted">(kosongkan untuk diskon otomatis tanpa
                kode)</small></label>
        <input type="text" name="code" value="{{ old('code', $d->code ?? '') }}" class="form-control"
            style="text-transform:uppercase">
        @error('code')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Tipe Diskon</label>
        <select name="discount_type" class="form-select" required>
            <option value="percent" @selected(old('discount_type', $d->discount_type ?? 'percent') === 'percent')>Persen (%)</option>
            <option value="fixed" @selected(old('discount_type', $d->discount_type ?? '') === 'fixed')>Nominal (Rp)</option>
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Nilai Diskon</label>
        <input type="number" step="0.01" name="discount_value"
            value="{{ old('discount_value', $d->discount_value ?? '') }}" class="form-control" required>
        @error('discount_value')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Maks Potongan (Rp) <small class="text-muted">khusus tipe persen</small></label>
        <input type="number" step="0.01" name="max_discount_amount"
            value="{{ old('max_discount_amount', $d->max_discount_amount ?? '') }}" class="form-control">
    </div>

    <div class="col-md-6">
        <label class="form-label">Berlaku untuk Paket</label>
        <select name="plan_id" class="form-select">
            <option value="">Semua paket</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}" @selected(old('plan_id', $d->plan_id ?? '') == $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Berlaku Dari</label>
        <input type="date" name="valid_from"
            value="{{ old('valid_from', optional($d->valid_from ?? null)->format('Y-m-d')) }}" class="form-control">
    </div>

    <div class="col-md-3">
        <label class="form-label">Berlaku Sampai</label>
        <input type="date" name="valid_until"
            value="{{ old('valid_until', optional($d->valid_until ?? null)->format('Y-m-d')) }}" class="form-control">
    </div>

    <div class="col-md-4">
        <label class="form-label">Maks Pemakaian <small class="text-muted">kosongkan = tanpa batas</small></label>
        <input type="number" name="max_usage" value="{{ old('max_usage', $d->max_usage ?? '') }}"
            class="form-control">
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                @checked(old('is_active', $d->is_active ?? true))>
            <label class="form-check-label" for="is_active">Aktifkan voucher</label>
        </div>
    </div>
</div>
