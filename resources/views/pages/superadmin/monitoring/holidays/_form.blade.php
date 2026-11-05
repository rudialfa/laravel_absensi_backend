{{--
    Field libur — dipakai create & edit. Variabel: $typeLabels, $holiday (edit)
--}}
@php $h = $holiday ?? null; @endphp

<div class="form-row">
    <div class="form-group col-md-8">
        <label>Nama Libur <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $h->name ?? '') }}" maxlength="255"
            class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Hari Raya Idul Fitri" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="form-group col-md-4">
        <label>Jenis <span class="text-danger">*</span></label>
        <select name="type" class="form-control @error('type') is-invalid @enderror" required>
            @foreach ($typeLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $h->type ?? 'national') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label>Mulai <span class="text-danger">*</span></label>
        <input type="date" name="start_date" id="start_date"
            value="{{ old('start_date', optional($h?->start_date)->format('Y-m-d')) }}"
            class="form-control @error('start_date') is-invalid @enderror" required>
        @error('start_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="form-group col-md-6">
        <label>Selesai <span class="text-danger">*</span></label>
        <input type="date" name="end_date" id="end_date"
            value="{{ old('end_date', optional($h?->end_date)->format('Y-m-d')) }}"
            class="form-control @error('end_date') is-invalid @enderror" required>
        <small class="text-muted">Sama dengan tanggal mulai untuk libur 1 hari.</small>
        @error('end_date')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="form-group">
    <label>Catatan</label>
    <textarea name="note" class="form-control @error('note') is-invalid @enderror" rows="2" maxlength="1000"
        placeholder="Opsional, ikut tampil di notifikasi karyawan">{{ old('note', $h->note ?? '') }}</textarea>
    @error('note')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="custom-control custom-checkbox">
    <input type="hidden" name="notify" value="0">
    <input type="checkbox" name="notify" value="1" class="custom-control-input" id="notify"
        @checked(old('notify', true))>
    <label class="custom-control-label" for="notify">Kirim notifikasi ke karyawan (hanya untuk libur yang belum
        lewat)</label>
</div>

@push('scripts')
    <script>
        // Tanggal selesai otomatis ikut tanggal mulai kalau masih kosong / lebih awal
        document.getElementById('start_date').addEventListener('change', function() {
            const end = document.getElementById('end_date');
            if (!end.value || end.value < this.value) end.value = this.value;
            end.min = this.value;
        });
    </script>
@endpush
