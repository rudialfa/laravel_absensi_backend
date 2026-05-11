@extends('layouts.employee')
@section('title','Buat Laporan Bulanan')
@section('breadcrumb')
    <a href="{{ route('employee.monthly-report.index') }}">Laporan Bulanan</a>
    <span class="sep">/</span><span class="current">Buat</span>
@endsection
@section('content')
<div class="page-title">Buat Laporan Bulanan</div>
<div class="page-sub">Laporan disimpan sebagai Draft — submit ke HR setelah selesai.</div>

<div class="card" style="max-width:700px">
    <form method="POST" action="{{ route('employee.monthly-report.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div class="form-group">
                <label class="lbl">Bulan <span style="color:var(--c-danger)">*</span></label>
                <select name="month" required>
                    @php $bulan=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; @endphp
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" {{ old('month', now()->month) == $m ? 'selected':'' }}>{{ $bulan[$m] }}</option>
                    @endforeach
                </select>
                @error('month')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="lbl">Tahun <span style="color:var(--c-danger)">*</span></label>
                <select name="year" required>
                    @foreach([now()->year, now()->year-1] as $y)
                        <option value="{{ $y }}" {{ old('year', now()->year) == $y ? 'selected':'' }}>{{ $y }}</option>
                    @endforeach
                </select>
                @error('year')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Target Bulan Ini <span style="color:var(--c-danger)">*</span></label>
                <textarea name="target" rows="3" placeholder="Apa yang ingin kamu capai bulan ini?" required>{{ old('target') }}</textarea>
                @error('target')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Pencapaian <span style="color:var(--c-danger)">*</span></label>
                <textarea name="achievement" rows="3" placeholder="Apa yang sudah berhasil dicapai?" required>{{ old('achievement') }}</textarea>
                @error('achievement')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Masalah / Hambatan <span style="color:var(--c-danger)">*</span></label>
                <textarea name="problem" rows="3" placeholder="Kendala atau masalah yang dihadapi..." required>{{ old('problem') }}</textarea>
                @error('problem')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Solusi <span style="color:var(--c-danger)">*</span></label>
                <textarea name="solution" rows="3" placeholder="Solusi yang sudah atau akan dilakukan..." required>{{ old('solution') }}</textarea>
                @error('solution')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Lampiran (opsional)</label>
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf">
                <div class="form-hint">JPG/PNG/PDF, maks 5MB</div>
                @error('attachment')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:8px">
            <button type="submit" class="btn btn-primary">Simpan sebagai Draft</button>
            <a href="{{ route('employee.monthly-report.index') }}" class="btn">Batal</a>
        </div>
    </form>
</div>
@endsection