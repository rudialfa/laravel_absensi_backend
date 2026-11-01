@php
    $typeLabels = [
        'terms_of_service' => 'Syarat & Ketentuan',
        'privacy_policy' => 'Kebijakan Privasi',
        'refund_policy' => 'Kebijakan Pengembalian Dana',
        'other' => 'Lainnya',
    ];
    $p = $policy ?? null;
@endphp

<div class="form-group">
    <label>Jenis Kebijakan</label>
    @if ($p)
        <input type="text" class="form-control" value="{{ $typeLabels[$p->type] ?? $p->type }}" disabled>
        <small class="text-muted">Jenis tidak bisa diubah. Buat versi baru kalau salah jenis.</small>
    @else
        <select name="type" class="form-control" required>
            <option value="">Pilih jenis</option>
            @foreach ($typeLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('type', request('type')) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')
            <small class="text-danger">{{ $message }}</small>
        @enderror
    @endif
</div>

<div class="form-row">
    <div class="form-group col-md-9">
        <label>Judul</label>
        <input type="text" name="title" value="{{ old('title', $p->title ?? '') }}" class="form-control"
            placeholder="Contoh: Syarat & Ketentuan Penggunaan" required>
        @error('title')
            <small class="text-danger">{{ $message }}</small>
        @enderror
    </div>
    <div class="form-group col-md-3">
        <label>Versi</label>
        <input type="text" name="version" value="{{ old('version', $p->version ?? '1.0') }}" class="form-control"
            placeholder="1.0" required>
        @error('version')
            <small class="text-danger">{{ $message }}</small>
        @enderror
    </div>
</div>

<div class="form-group">
    <label>Isi</label>
    <textarea name="content" rows="22" class="form-control" style="height:auto; font-family:monospace; font-size:13px"
        required>{{ old('content', $p->content ?? '') }}</textarea>
    @error('content')
        <small class="text-danger">{{ $message }}</small>
    @enderror
    <small class="text-muted d-block mt-2">
        Format sederhana yang dikenali aplikasi:
        <code># Judul besar</code>, <code>## Sub judul</code>, <code>- poin</code>, <code>1. poin bernomor</code>,
        <code>**tebal**</code>. Pisahkan paragraf dengan baris kosong.
    </small>
</div>
