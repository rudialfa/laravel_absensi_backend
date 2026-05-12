{{-- resources/views/pages/santri/permission/create.blade.php --}}
@extends('layouts.Santri')
@section('title','Ajukan Izin')

@push('styles')
@include('pages.santri._shared_css')
@endpush

@section('content')
<div class="container-fluid s-page" style="max-width:640px;">
    <div class="s-header">
        <div>
            <h1 class="s-title">📝 Ajukan Izin</h1>
            <p class="s-sub">Lengkapi form pengajuan izin di bawah ini</p>
        </div>
        <a href="{{ route('pages.santri.permission.index') }}" class="s-btn s-btn-outline s-btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>
    </div>

    <div class="s-card">
        <form method="POST" action="{{ route('pages.santri.permission.store') }}" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom:16px;">
                <label class="s-label">Tanggal Izin <span style="color:var(--red);">*</span></label>
                <input type="date" name="date_permission" class="s-control" value="{{ old('date_permission') }}" required min="{{ now()->toDateString() }}">
                @error('date_permission')<p style="color:var(--red);font-size:.78rem;margin-top:4px;">{{ $message }}</p>@enderror
            </div>
            <div style="margin-bottom:16px;">
                <label class="s-label">Alasan Izin <span style="color:var(--red);">*</span></label>
                <textarea name="reason" class="s-control" rows="4" placeholder="Jelaskan alasan izin Anda dengan jelas…" required maxlength="500">{{ old('reason') }}</textarea>
                <p style="font-size:.75rem;color:var(--gray-400);margin-top:4px;">Maks. 500 karakter</p>
                @error('reason')<p style="color:var(--red);font-size:.78rem;margin-top:4px;">{{ $message }}</p>@enderror
            </div>
            <div style="margin-bottom:22px;">
                <label class="s-label">Bukti / Lampiran (opsional)</label>
                <input type="file" name="image" class="s-control" accept="image/jpg,image/jpeg,image/png">
                <p style="font-size:.75rem;color:var(--gray-400);margin-top:4px;">Format: JPG, JPEG, PNG • Maks 2MB</p>
                @error('image')<p style="color:var(--red);font-size:.78rem;margin-top:4px;">{{ $message }}</p>@enderror
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <a href="{{ route('pages.santri.permission.index') }}" class="s-btn s-btn-outline">Batal</a>
                <button type="submit" class="s-btn s-btn-primary">📤 Ajukan Izin</button>
            </div>
        </form>
    </div>
</div>
@endsection
