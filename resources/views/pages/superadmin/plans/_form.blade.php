{{--
    Form paket langganan — dipakai create & edit.
    Variabel: $plan (edit), $template (create dari salinan), $nextSortOrder (create), $isInUse (edit)
--}}
@php
    $p = $plan ?? ($template ?? null);
    $isEdit = isset($plan);
    $inUse = $isInUse ?? false;
    $presets = [7 => '7 hari', 30 => '1 bulan', 90 => '3 bulan', 180 => '6 bulan', 365 => '1 tahun'];
@endphp

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <h4>Informasi Paket</h4>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-7">
                        <label>Nama Paket <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="plan-name"
                            value="{{ old('name', $isEdit ? $p->name : ($p ? $p->name . ' (salinan)' : '')) }}"
                            class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Bulanan"
                            required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-5">
                        <label>Slug (kode)</label>
                        <input type="text" name="slug" id="plan-slug"
                            value="{{ old('slug', $isEdit ? $p->slug : '') }}"
                            class="form-control @error('slug') is-invalid @enderror" placeholder="otomatis dari nama">
                        @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if ($isEdit && $inUse)
                            <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Paket sedang
                                dipakai. Jangan ubah slug kecuali yakin.</small>
                        @else
                            <small class="text-muted">Huruf kecil, angka, tanda minus. Contoh: monthly</small>
                        @endif
                    </div>
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" style="height:90px"
                        placeholder="Ditampilkan di halaman pilih paket di aplikasi">{{ old('description', $p->description ?? '') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Durasi (hari) <span class="text-danger">*</span></label>
                        <input type="number" name="duration_days" id="duration_days" min="1" max="3650"
                            value="{{ old('duration_days', $p->duration_days ?? 30) }}"
                            class="form-control @error('duration_days') is-invalid @enderror" required>
                        <div class="mt-2">
                            @foreach ($presets as $days => $label)
                                <button type="button" class="btn btn-sm btn-light mb-1 js-duration"
                                    data-days="{{ $days }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        @error('duration_days')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Harga (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="price" id="price" step="1" min="0"
                            value="{{ old('price', isset($p->price) ? (int) $p->price : '') }}"
                            class="form-control @error('price') is-invalid @enderror" required>
                        <small class="text-muted" id="price-hint"></small>
                        @error('price')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @if ($isEdit && $inUse)
                            <small class="text-muted d-block">Harga baru hanya berlaku untuk invoice berikutnya.</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <h4>Tampilan &amp; Status</h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="custom-switch pl-0">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="custom-switch-input"
                            @checked(old('is_active', $isEdit ? $p->is_active : true))>
                        <span class="custom-switch-indicator"></span>
                        <span class="custom-switch-description">Aktif (tampil di aplikasi)</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="custom-switch pl-0">
                        <input type="hidden" name="is_free" value="0">
                        <input type="checkbox" name="is_free" value="1" id="is_free" class="custom-switch-input"
                            @checked(old('is_free', $p->is_free ?? false))>
                        <span class="custom-switch-indicator"></span>
                        <span class="custom-switch-description">Paket gratis (trial)</span>
                    </label>
                    <small class="text-muted d-block">Dipakai otomatis saat tenant baru mulai trial.</small>
                </div>
                <div class="form-group">
                    <label class="custom-switch pl-0">
                        <input type="hidden" name="is_popular" value="0">
                        <input type="checkbox" name="is_popular" value="1" class="custom-switch-input"
                            @checked(old('is_popular', $p->is_popular ?? false))>
                        <span class="custom-switch-indicator"></span>
                        <span class="custom-switch-description">Badge "Terpopuler"</span>
                    </label>
                </div>
                <div class="form-group">
                    <label>Label Hemat</label>
                    <input type="text" name="saving_label" maxlength="50"
                        value="{{ old('saving_label', $p->saving_label ?? '') }}"
                        class="form-control @error('saving_label') is-invalid @enderror"
                        placeholder="Contoh: Hemat ~16%">
                    @error('saving_label')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group mb-0">
                    <label>Urutan Tampil</label>
                    <input type="number" name="sort_order" min="0" max="255"
                        value="{{ old('sort_order', $isEdit ? $p->sort_order : $nextSortOrder ?? 0) }}"
                        class="form-control @error('sort_order') is-invalid @enderror">
                    <small class="text-muted">Angka kecil tampil lebih dulu.</small>
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function() {
            const price = document.getElementById('price');
            const days = document.getElementById('duration_days');
            const isFree = document.getElementById('is_free');
            const hint = document.getElementById('price-hint');
            const name = document.getElementById('plan-name');
            const slug = document.getElementById('plan-slug');

            function updateHint() {
                const p = parseFloat(price.value) || 0;
                const d = parseInt(days.value) || 0;
                if (isFree.checked) {
                    hint.textContent = 'Paket gratis — harga otomatis Rp 0';
                } else if (p > 0 && d > 0) {
                    const perMonth = Math.round(p / d * 30);
                    hint.textContent = '≈ Rp ' + perMonth.toLocaleString('id-ID') + ' per 30 hari';
                } else {
                    hint.textContent = '';
                }
            }

            function syncFree() {
                price.readOnly = isFree.checked;
                if (isFree.checked) price.value = 0;
                updateHint();
            }

            document.querySelectorAll('.js-duration').forEach(btn =>
                btn.addEventListener('click', () => {
                    days.value = btn.dataset.days;
                    updateHint();
                }));

            [price, days].forEach(el => el.addEventListener('input', updateHint));
            isFree.addEventListener('change', syncFree);
            syncFree();

            // Placeholder slug mengikuti nama
            const toSlug = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            name.addEventListener('input', () => {
                slug.placeholder = toSlug(name.value) || 'otomatis dari nama';
            });
        })();
    </script>
@endpush
