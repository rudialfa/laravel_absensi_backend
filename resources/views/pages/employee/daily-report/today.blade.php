@extends('layouts.employee')

@section('title', 'Laporan Hari Ini')
@section('breadcrumb')
    <a href="{{ route('employee.daily-report.index') }}">Laporan Harian</a>
    <span class="sep">/</span><span class="current">Hari Ini</span>
@endsection

@section('content')
<div class="page-title">Laporan Hari Ini</div>
<div class="page-sub">{{ now()->isoFormat('dddd, D MMMM Y') }}</div>

@php
    $report  = $today['data'] ?? null;
    $info    = $today['info'] ?? [];
    $hasPagi = $info['submitted_morning'] ?? false;
    $hasSore = $info['submitted_evening'] ?? false;
@endphp

<div class="two-col" style="align-items:start">

    {{-- ── Panel Target Pagi ────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">🌅 Target Pagi</span>
            @if($hasPagi)
                <span class="badge badge-success">Sudah diisi</span>
            @else
                <span class="badge badge-warning">Belum diisi</span>
            @endif
        </div>

        @if($hasPagi && $report)
            <div style="background:var(--c-bg);border-radius:var(--radius-sm);padding:14px;font-size:13px;line-height:1.6">
                {{ $report['target'] }}
            </div>
            @if($report['attachment'])
                <div style="margin-top:12px">
                    <img src="{{ asset($report['attachment']) }}" alt="Lampiran" style="max-width:100%;border-radius:var(--radius-sm);border:1px solid var(--c-border)">
                </div>
            @endif
        @else
            <form method="POST" action="{{ route('employee.daily-report.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group" style="margin-bottom:14px">
                    <label class="lbl">Target hari ini <span style="color:var(--c-danger)">*</span></label>
                    <textarea name="target" rows="4" placeholder="Tuliskan target yang ingin kamu capai hari ini..." required>{{ old('target') }}</textarea>
                    @error('target')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group" style="margin-bottom:16px">
                    <label class="lbl">Lampiran (opsional)</label>
                    <input type="file" name="attachment" accept="image/jpg,image/jpeg,image/png">
                    <div class="form-hint">JPG/PNG, maks 2MB</div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Submit Target Pagi</button>
            </form>
        @endif
    </div>

    {{-- ── Panel Pencapaian Sore ────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">🌆 Pencapaian Sore</span>
            @if($hasSore)
                <span class="badge badge-success">Sudah diisi</span>
            @elseif($hasPagi)
                <span class="badge badge-warning">Belum diisi</span>
            @else
                <span class="badge badge-gray">Tunggu target pagi</span>
            @endif
        </div>

        @if($hasSore && $report)
            <div class="info-list">
                <div class="row">
                    <span class="lbl">Status</span>
                    <span class="val">
                        @if($report['is_achieved'])
                            <span class="badge badge-success">Tercapai ✓</span>
                        @else
                            <span class="badge badge-danger">Tidak Tercapai</span>
                        @endif
                    </span>
                </div>
            </div>
            <div style="background:var(--c-bg);border-radius:var(--radius-sm);padding:14px;font-size:13px;line-height:1.6;margin-top:12px">
                {{ $report['achievement'] }}
            </div>
            @if($report['reason_not_achieved'])
                <div style="margin-top:10px;padding:10px;background:var(--c-warning-bg);border-radius:var(--radius-sm);font-size:12px;color:var(--c-warning)">
                    <strong>Alasan:</strong> {{ $report['reason_not_achieved'] }}
                </div>
            @endif

        @elseif($hasPagi && $report)
            <form method="POST" action="{{ route('employee.daily-report.update', $report['id']) }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group" style="margin-bottom:14px">
                    <label class="lbl">Pencapaian <span style="color:var(--c-danger)">*</span></label>
                    <textarea name="achievement" rows="3" placeholder="Apa yang berhasil dicapai hari ini?" required>{{ old('achievement') }}</textarea>
                    @error('achievement')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label class="lbl">Apakah target tercapai? <span style="color:var(--c-danger)">*</span></label>
                    <select name="is_achieved" id="is_achieved" required onchange="toggleReason(this.value)">
                        <option value="">-- Pilih --</option>
                        <option value="1" {{ old('is_achieved') == '1' ? 'selected' : '' }}>Ya, target tercapai</option>
                        <option value="0" {{ old('is_achieved') == '0' ? 'selected' : '' }}>Tidak, belum tercapai</option>
                    </select>
                </div>
                <div class="form-group" id="reason-box" style="display:none;margin-bottom:14px">
                    <label class="lbl">Alasan tidak tercapai</label>
                    <textarea name="reason_not_achieved" rows="2" placeholder="Jelaskan hambatan atau kendalanya...">{{ old('reason_not_achieved') }}</textarea>
                </div>
                <div class="form-group" style="margin-bottom:16px">
                    <label class="lbl">Lampiran (opsional)</label>
                    <input type="file" name="attachment" accept="image/jpg,image/jpeg,image/png">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Submit Pencapaian Sore</button>
            </form>
        @else
            <div class="empty" style="padding:32px">
                <div class="empty-icon">🌅</div>
                <div class="empty-text">Isi target pagi terlebih dahulu.</div>
            </div>
        @endif
    </div>
</div>

<div style="margin-top:4px">
    <a href="{{ route('employee.daily-report.index') }}" class="btn btn-sm">← Kembali ke Riwayat</a>
</div>
@endsection

@push('scripts')
<script>
function toggleReason(val) {
    document.getElementById('reason-box').style.display = val === '0' ? 'block' : 'none';
}
// Restore on old input
const sel = document.getElementById('is_achieved');
if(sel) toggleReason(sel.value);
</script>
@endpush