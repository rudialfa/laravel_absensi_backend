@extends('layouts.employee')
@section('title','Ajukan Cuti')
@section('breadcrumb')
    <a href="{{ route('employee.leave.index') }}">Cuti</a>
    <span class="sep">/</span><span class="current">Ajukan</span>
@endsection
@section('content')
<div class="page-title">Ajukan Cuti Baru</div>
<div class="page-sub">Pengajuan cuti akan menunggu persetujuan HR.</div>

<div class="card" style="max-width:560px">
    <form method="POST" action="{{ route('employee.leave.store') }}">
        @csrf
        <div class="form-grid">
            <div class="form-group form-full">
                <label class="lbl">Jenis Cuti <span style="color:var(--c-danger)">*</span></label>
                <select name="type" required>
                    <option value="annual"    {{ old('type')=='annual'   ?'selected':'' }}>Cuti Tahunan</option>
                    <option value="sick"      {{ old('type')=='sick'     ?'selected':'' }}>Cuti Sakit</option>
                    <option value="maternity" {{ old('type')=='maternity'?'selected':'' }}>Cuti Melahirkan</option>
                    <option value="important" {{ old('type')=='important'?'selected':'' }}>Kepentingan Penting</option>
                    <option value="other"     {{ old('type')=='other'    ?'selected':'' }}>Lainnya</option>
                </select>
                @error('type')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="lbl">Tanggal Mulai <span style="color:var(--c-danger)">*</span></label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required min="{{ now()->toDateString() }}">
                @error('start_date')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="lbl">Tanggal Selesai <span style="color:var(--c-danger)">*</span></label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required min="{{ now()->toDateString() }}">
                @error('end_date')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Alasan (opsional)</label>
                <textarea name="reason" rows="3" placeholder="Jelaskan alasan pengajuan cuti...">{{ old('reason') }}</textarea>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:8px">
            <button type="submit" class="btn btn-primary">Ajukan Cuti</button>
            <a href="{{ route('employee.leave.index') }}" class="btn">Batal</a>
        </div>
    </form>
</div>
@endsection