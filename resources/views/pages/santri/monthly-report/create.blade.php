{{-- resources/views/pages/santri/monthly-report/create.blade.php --}}
@extends('layouts.Santri')
@section('title','Buat Laporan Bulanan')

@push('styles')
@include('pages.santri._shared_css')
<style>
.form-section { background:#fff; border:1px solid var(--gray-200); border-radius:var(--radius); padding:22px; margin-bottom:18px; box-shadow:var(--shadow); }
.form-section-title { font-size:.9rem; font-weight:700; color:var(--gray-700); margin:0 0 16px; padding-bottom:10px; border-bottom:2px solid var(--gray-100); }
</style>
@endpush

@section('content')
<div class="container-fluid s-page">
    <div class="s-header">
        <div>
            <h1 class="s-title">✏️ Buat Laporan Bulanan</h1>
            <p class="s-sub">Isi form di bawah ini dengan lengkap</p>
        </div>
        <a href="{{ route('pages.santri.monthly-report.index') }}" class="s-btn s-btn-outline s-btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>
    </div>

    <form method="POST" action="{{ route('pages.santri.monthly-report.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-section">
            <p class="form-section-title">📅 Periode Laporan</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="s-label">Bulan <span style="color:var(--red);">*</span></label>
                    <select name="month" class="s-control" required>
                        @for($m=1;$m<=12;$m++)
                        <option value="{{ $m }}" {{ old('month',now()->month)==$m?'selected':'' }}>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                        @endfor
                    </select>
                    @error('month')<p style="color:var(--red);font-size:.78rem;margin-top:4px;">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="s-label">Tahun <span style="color:var(--red);">*</span></label>
                    <select name="year" class="s-control" required>
                        @for($y=now()->year;$y>=now()->year-2;$y--)
                        <option value="{{ $y }}" {{ old('year',now()->year)==$y?'selected':'' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    @error('year')<p style="color:var(--red);font-size:.78rem;margin-top:4px;">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="form-section">
            <p class="form-section-title">🎯 Rencana & Capaian</p>
            @foreach([['target','Target Bulan Ini','Tuliskan target yang ingin dicapai bulan ini…'],['achievement','Pencapaian','Ceritakan apa yang berhasil dicapai bulan ini…'],['problem','Kendala / Masalah','Kendala apa yang ditemui selama bulan ini?'],['solution','Solusi','Bagaimana cara mengatasi kendala tersebut?']] as [$name,$label,$placeholder])
            <div style="margin-bottom:16px;">
                <label class="s-label">{{ $label }} <span style="color:var(--red);">*</span></label>
                <textarea name="{{ $name }}" class="s-control" rows="3" placeholder="{{ $placeholder }}" required>{{ old($name) }}</textarea>
                @error($name)<p style="color:var(--red);font-size:.78rem;margin-top:4px;">{{ $message }}</p>@enderror
            </div>
            @endforeach
        </div>

        <div class="form-section">
            <p class="form-section-title">📎 Lampiran</p>
            <label class="s-label">File (JPG, PNG, PDF • maks 5MB)</label>
            <input type="file" name="attachment" class="s-control" accept="image/jpg,image/jpeg,image/png,application/pdf">
            @error('attachment')<p style="color:var(--red);font-size:.78rem;margin-top:4px;">{{ $message }}</p>@enderror
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <a href="{{ route('pages.santri.monthly-report.index') }}" class="s-btn s-btn-outline">Batal</a>
            <button type="submit" class="s-btn s-btn-primary">💾 Simpan Laporan</button>
        </div>
    </form>
</div>
@endsection
