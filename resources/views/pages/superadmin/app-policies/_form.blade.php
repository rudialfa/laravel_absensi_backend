{{--
    Form kebijakan. Variabel: $typeLabels, dan salah satu:
      - $policy  (edit draft)
      - $prefill (buat baru: type, title, content, version)
--}}
@php
    $p = $policy ?? null;
    $v = fn($key, $default = '') => old($key, $p ? $p->{$key} : ($prefill[$key] ?? $default));
@endphp

<div class="form-row">
    <div class="form-group col-md-4">
        <label>Jenis Kebijakan <span class="text-danger">*</span></label>
        @if ($p)
            <input type="text" class="form-control" value="{{ $typeLabels[$p->type] ?? $p->type }}" disabled>
            <small class="text-muted">Jenis tidak bisa diubah.</small>
        @else
            <select name="type" class="form-control @error('type') is-invalid @enderror" required>
                <option value="">Pilih jenis</option>
                @foreach ($typeLabels as $value => $label)
                    <option value="{{ $value }}" @selected($v('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @endif
    </div>
    <div class="form-group col-md-6">
        <label>Judul <span class="text-danger">*</span></label>
        <input type="text" name="title" value="{{ $v('title') }}" class="form-control @error('title') is-invalid @enderror" maxlength="255"
            placeholder="Contoh: Syarat & Ketentuan Penggunaan" required>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="form-group col-md-2">
        <label>Versi <span class="text-danger">*</span></label>
        <input type="text" name="version" value="{{ $v('version', '1.0') }}" class="form-control @error('version') is-invalid @enderror" maxlength="20" placeholder="1.0" required>
        @error('version')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="form-group mb-0">
    <label>Isi <span class="text-danger">*</span></label>
    <textarea name="content" rows="24" class="form-control @error('content') is-invalid @enderror" style="height:auto; font-family:monospace; font-size:13px"
        required>{{ $v('content') }}</textarea>
    @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <small class="text-muted d-block mt-2">
        Format yang dikenali aplikasi:
        <code># Judul besar</code>, <code>## Sub judul</code>, <code>- poin</code>, <code>1. poin bernomor</code>, <code>**tebal**</code>.
        Pisahkan paragraf dengan baris kosong. Hasilnya bisa dilihat di halaman pratinjau setelah disimpan.
    </small>
</div>
