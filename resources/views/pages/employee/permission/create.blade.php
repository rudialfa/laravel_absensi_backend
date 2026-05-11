@extends('layouts.employee')
@section('title','Ajukan Izin')
@section('breadcrumb')
    <a href="{{ route('employee.permission.index') }}">Izin</a>
    <span class="sep">/</span><span class="current">Ajukan</span>
@endsection
@section('content')
<div class="page-title">Ajukan Izin Tidak Masuk</div>
<div class="page-sub">Pengajuan izin akan menunggu persetujuan HR.</div>
<div class="card" style="max-width:520px">
    <form method="POST" action="{{ route('employee.permission.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div class="form-group form-full">
                <label class="lbl">Tanggal Izin <span style="color:var(--c-danger)">*</span></label>
                <input type="date" name="date_permission" value="{{ old('date_permission') }}" required>
                @error('date_permission')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Alasan <span style="color:var(--c-danger)">*</span></label>
                <textarea name="reason" rows="4" maxlength="500" placeholder="Jelaskan alasan izin kamu..." required>{{ old('reason') }}</textarea>
                @error('reason')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Bukti Pendukung (opsional)</label>
                <input type="file" name="image" accept="image/jpg,image/jpeg,image/png">
                <div class="form-hint">Foto surat dokter, undangan, dll. JPG/PNG, maks 2MB.</div>
                @error('image')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:8px">
            <button type="submit" class="btn btn-primary">Ajukan Izin</button>
            <a href="{{ route('employee.permission.index') }}" class="btn">Batal</a>
        </div>
    </form>
</div>
@endsection