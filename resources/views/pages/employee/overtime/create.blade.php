@extends('layouts.employee')
@section('title','Ajukan Lembur')
@section('breadcrumb')
    <a href="{{ route('employee.overtime.index') }}">Lembur</a>
    <span class="sep">/</span><span class="current">Ajukan</span>
@endsection
@section('content')
<div class="page-title">Ajukan Lembur</div>
<div class="page-sub">Durasi dihitung otomatis dari jam mulai dan jam selesai.</div>
<div class="card" style="max-width:560px">
    <form method="POST" action="{{ route('employee.overtime.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div class="form-group form-full">
                <label class="lbl">Tanggal Lembur <span style="color:var(--c-danger)">*</span></label>
                <input type="date" name="date" value="{{ old('date') }}" required>
                @error('date')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="lbl">Jam Mulai <span style="color:var(--c-danger)">*</span></label>
                <input type="time" name="start_time" value="{{ old('start_time') }}" required>
                @error('start_time')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="lbl">Jam Selesai <span style="color:var(--c-danger)">*</span></label>
                <input type="time" name="end_time" value="{{ old('end_time') }}" required>
                @error('end_time')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Alasan Lembur</label>
                <textarea name="reason" rows="3" placeholder="Jelaskan pekerjaan yang dikerjakan saat lembur...">{{ old('reason') }}</textarea>
            </div>
            <div class="form-group form-full">
                <label class="lbl">Bukti Lembur (opsional)</label>
                <input type="file" name="evidence_image" accept="image/jpg,image/jpeg,image/png">
                <div class="form-hint">Foto bukti pekerjaan lembur. JPG/PNG, maks 2MB.</div>
                @error('evidence_image')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>
        <div id="duration-preview" style="display:none;padding:10px;background:var(--c-info-bg);border-radius:var(--radius-sm);margin-bottom:14px;font-size:13px;color:var(--c-info)">
            ⏱ Durasi: <strong id="duration-text"></strong>
        </div>
        <div style="display:flex;gap:10px">
            <button type="submit" class="btn btn-primary">Ajukan Lembur</button>
            <a href="{{ route('employee.overtime.index') }}" class="btn">Batal</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function calcDuration() {
    const s = document.querySelector('[name=start_time]').value;
    const e = document.querySelector('[name=end_time]').value;
    if (!s || !e) return;
    let [sh,sm] = s.split(':').map(Number), [eh,em] = e.split(':').map(Number);
    let mins = (eh*60+em) - (sh*60+sm);
    if (mins < 0) mins += 1440;
    if (mins > 0) {
        const h = Math.floor(mins/60), m = mins%60;
        document.getElementById('duration-text').textContent = (h?h+' jam ':'')+m+' menit';
        document.getElementById('duration-preview').style.display = 'block';
    }
}
document.querySelector('[name=start_time]').addEventListener('change', calcDuration);
document.querySelector('[name=end_time]').addEventListener('change', calcDuration);
</script>
@endpush