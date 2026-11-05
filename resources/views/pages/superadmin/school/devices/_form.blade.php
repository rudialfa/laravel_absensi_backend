{{-- Form kiosk. Variabel: $device, $schools, $classes --}}
@php
    $companyId = old('company_id', $device->company_id);
    $classId = old('class_id', $device->class_id);
@endphp

<div class="form-group">
    <label>Sekolah / Pesantren <span class="text-danger">*</span></label>
    <select name="company_id" id="deviceCompany" class="form-control @error('company_id') is-invalid @enderror" required>
        <option value="">— Pilih sekolah —</option>
        @foreach ($schools as $s)
            <option value="{{ $s->id }}" @selected((string) $companyId === (string) $s->id)>
                {{ $s->name }}{{ $s->type === 'pesantren' ? ' (Pesantren)' : '' }}{{ $s->is_active ? '' : ' — suspend' }}
            </option>
        @endforeach
    </select>
    @error('company_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if ($device->exists)
        <small class="form-text text-warning">Kalau sekolah diganti, token otomatis dibuat ulang dan harus dipasang lagi
            di perangkat.</small>
    @endif
</div>

<div class="form-group">
    <label>Kelas</label>
    <select name="class_id" id="deviceClass" class="form-control @error('class_id') is-invalid @enderror">
        <option value="">Umum — guru memilih kelas di layar kiosk</option>
        @foreach ($classes as $c)
            <option value="{{ $c->id }}" data-company="{{ $c->company_id }}" @selected((string) $classId === (string) $c->id)>
                {{ $c->name }} · {{ $c->academic_year }}{{ $c->is_active ? '' : ' (arsip)' }}
            </option>
        @endforeach
    </select>
    @error('class_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <small class="form-text text-muted">Kalau diisi, kiosk hanya bisa dipakai absen kelas ini.</small>
</div>

<div class="form-group">
    <label>Nama Kiosk <span class="text-danger">*</span></label>
    <input type="text" name="name" value="{{ old('name', $device->name) }}" maxlength="100" required
        class="form-control @error('name') is-invalid @enderror"
        placeholder="Contoh: Kiosk Kelas 1A / Tablet Gerbang Depan">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label>ID Perangkat</label>
    <input type="text" name="device_identifier" value="{{ old('device_identifier', $device->device_identifier) }}"
        maxlength="255" class="form-control @error('device_identifier') is-invalid @enderror"
        placeholder="Nomor seri / IMEI (opsional)">
    @error('device_identifier')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <small class="form-text text-muted">Untuk mencocokkan perangkat fisik kalau hilang atau diganti.</small>
</div>

<div class="form-group mb-0">
    <input type="hidden" name="is_active" value="0">
    <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="deviceActive" name="is_active" value="1"
            @checked(old('is_active', $device->is_active ?? true))>
        <label class="custom-control-label" for="deviceActive">Aktif (kiosk bisa dipakai absen)</label>
    </div>
</div>

@push('scripts')
    <script>
        $(function() {
            const $company = $('#deviceCompany');
            const $class = $('#deviceClass');

            function filterClasses() {
                const companyId = $company.val();
                $class.find('option[data-company]').each(function() {
                    const show = companyId && String($(this).data('company')) === String(companyId);
                    $(this).prop('hidden', !show).prop('disabled', !show);
                });
                const $selected = $class.find('option:selected');
                if ($selected.prop('disabled')) $class.val('');
            }

            $company.on('change', filterClasses);
            filterClasses();
        });
    </script>
@endpush
