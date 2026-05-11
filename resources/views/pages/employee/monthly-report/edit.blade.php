@extends('layouts.employee')
@section('title','Edit Laporan Bulanan')
@section('breadcrumb')
    <a href="{{ route('employee.monthly-report.index') }}">Laporan Bulanan</a>
    <span class="sep">/</span>
    <a href="{{ route('employee.monthly-report.show', $report['id']) }}">Detail</a>
    <span class="sep">/</span><span class="current">Edit</span>
@endsection
@section('content')
@php $bulan=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; @endphp
<div class="page-title">Edit Laporan {{ $bulan[$report['month']] }} {{ $report['year'] }}</div>
<div class="page-sub">Status: <strong>{{ ucfirst($report['status']) }}</strong></div>

<div class="card" style="max-width:700px">
    <form method="POST" action="{{ route('employee.monthly-report.update', $report['id']) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div class="form-group form-full">
                <label class="lbl">Target</label>
                <textarea name="target" rows="3" required>{{ old('target', $report['target']) }}</textarea>
                @error('target')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Pencapaian</label>
                <textarea name="achievement" rows="3" required>{{ old('achievement', $report['achievement']) }}</textarea>
                @error('achievement')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Masalah</label>
                <textarea name="problem" rows="3" required>{{ old('problem', $report['problem']) }}</textarea>
                @error('problem')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Solusi</label>
                <textarea name="solution" rows="3" required>{{ old('solution', $report['solution']) }}</textarea>
                @error('solution')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group form-full">
                <label class="lbl">Ganti Lampiran (opsional)</label>
                @if($report['attachment'])
                    <div style="margin-bottom:8px;font-size:12px;color:var(--c-muted)">Lampiran saat ini: <a href="{{ asset($report['attachment']) }}" target="_blank" style="color:var(--c-primary)">Lihat</a></div>
                @endif
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf">
                <div class="form-hint">JPG/PNG/PDF, maks 5MB. Kosongkan jika tidak ingin mengganti.</div>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:8px">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="{{ route('employee.monthly-report.show', $report['id']) }}" class="btn">Batal</a>
        </div>
    </form>
</div>
@endsection