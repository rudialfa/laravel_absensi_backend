{{-- resources/views/pages/santri/daily-report/today.blade.php --}}
@extends('layouts.Santri')
@section('title','Laporan Harian Hari Ini')

@push('styles')
@include('pages.santri._shared_css')
<style>
.phase-card { border-radius: var(--radius); padding: 22px; margin-bottom: 18px; }
.phase-morning  { background: linear-gradient(135deg,#fff9e6,#fff3cc); border:1px solid #f0d980; }
.phase-evening  { background: linear-gradient(135deg,#e8f0fc,#d4e4fa); border:1px solid #a8c5f0; }
.phase-title    { font-size:1rem; font-weight:700; margin:0 0 4px; }
.phase-sub      { font-size:.8rem; color:var(--gray-500); margin:0 0 18px; }
.phase-morning .phase-title { color:#92680a; }
.phase-evening .phase-title { color:#1a4a8a; }
.attach-preview { max-width:200px; border-radius:8px; border:2px solid var(--gray-200); margin-top:10px; }
</style>
@endpush

@section('content')
<div class="container-fluid s-page">
    @if(session('success'))<div class="s-alert s-alert-success"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="s-alert s-alert-error"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ session('error') }}</div>@endif

    <div class="s-header">
        <div>
            <h1 class="s-title">📋 Laporan Harian</h1>
            <p class="s-sub">{{ now()->translatedFormat('l, d F Y') }}</p>
        </div>
        <a href="{{ route('pages.santri.daily-report.index') }}" class="s-btn s-btn-outline s-btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Riwayat
        </a>
    </div>

    @php
        $hasTarget      = !empty($today['target']);
        $hasAchievement = !empty($today['achievement']);
        $isAchieved     = $today['is_achieved'] ?? null;
        $reportId       = $today['id'] ?? null;
    @endphp

    {{-- ☀️ TARGET PAGI --}}
    <div class="phase-card phase-morning">
        <p class="phase-title">☀️ Target Pagi</p>
        <p class="phase-sub">Tuliskan target kegiatan yang ingin Anda capai hari ini</p>
        @if($hasTarget)
            <div style="background:rgba(255,255,255,.6);border-radius:8px;padding:12px 14px;font-size:.9rem;color:#5a3e00;border-left:3px solid var(--gold);">
                {{ $today['target'] }}
            </div>
            @if(!empty($today['target_attachment']))
                <img src="{{ $today['target_attachment'] }}" alt="Lampiran" class="attach-preview">
            @endif
            <div style="margin-top:12px;">
                <span class="s-badge s-badge-gold">✓ Target sudah diisi</span>
            </div>
        @else
            <form method="POST" action="{{ route('pages.santri.daily-report.store') }}" enctype="multipart/form-data">
                @csrf
                <div style="margin-bottom:12px;">
                    <label class="s-label">Target Hari Ini <span style="color:var(--red);">*</span></label>
                    <textarea name="target" class="s-control" rows="3" placeholder="Contoh: Menghafal 1 halaman Al-Quran, mengikuti semua kajian…" required>{{ old('target') }}</textarea>
                    @error('target')<p style="color:var(--red);font-size:.78rem;margin-top:4px;">{{ $message }}</p>@enderror
                </div>
                <div style="margin-bottom:16px;">
                    <label class="s-label">Lampiran (opsional)</label>
                    <input type="file" name="attachment" class="s-control" accept="image/jpg,image/jpeg,image/png">
                </div>
                <button type="submit" class="s-btn s-btn-gold">📤 Submit Target Pagi</button>
            </form>
        @endif
    </div>

    {{-- 🌙 PENCAPAIAN SORE --}}
    <div class="phase-card phase-evening">
        <p class="phase-title">🌙 Pencapaian Sore</p>
        <p class="phase-sub">Evaluasi target yang sudah Anda tuliskan pagi tadi</p>
        @if(!$hasTarget)
            <div style="padding:14px;background:rgba(255,255,255,.5);border-radius:8px;font-size:.85rem;color:var(--gray-600);">
                ℹ️ Isi target pagi terlebih dahulu sebelum mengisi pencapaian sore.
            </div>
        @elseif($hasAchievement)
            <div style="background:rgba(255,255,255,.6);border-radius:8px;padding:12px 14px;font-size:.9rem;color:#0f2d5a;border-left:3px solid var(--blue);">
                {{ $today['achievement'] }}
            </div>
            @if(!empty($today['reason_not_achieved']))
                <div style="margin-top:10px;font-size:.83rem;color:var(--gray-600);">
                    <strong>Alasan tidak tercapai:</strong> {{ $today['reason_not_achieved'] }}
                </div>
            @endif
            <div style="margin-top:12px;">
                @if($isAchieved)
                    <span class="s-badge s-badge-green">✅ Target Tercapai</span>
                @else
                    <span class="s-badge s-badge-red">❌ Belum Tercapai</span>
                @endif
            </div>
        @else
            <form method="POST" action="{{ route('pages.santri.daily-report.update', $reportId) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div style="margin-bottom:12px;">
                    <label class="s-label">Pencapaian <span style="color:var(--red);">*</span></label>
                    <textarea name="achievement" class="s-control" rows="3" placeholder="Ceritakan apa yang sudah Anda capai hari ini…" required>{{ old('achievement') }}</textarea>
                </div>
                <div style="margin-bottom:12px;">
                    <label class="s-label">Apakah Target Tercapai?</label>
                    <div style="display:flex;gap:16px;margin-top:6px;">
                        <label style="display:flex;align-items:center;gap:6px;font-size:.875rem;cursor:pointer;">
                            <input type="radio" name="is_achieved" value="1" {{ old('is_achieved')=='1'?'checked':'' }} required> Ya, tercapai
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:.875rem;cursor:pointer;">
                            <input type="radio" name="is_achieved" value="0" {{ old('is_achieved')=='0'?'checked':'' }}> Belum tercapai
                        </label>
                    </div>
                </div>
                <div style="margin-bottom:12px;" id="reasonWrap">
                    <label class="s-label">Alasan Tidak Tercapai</label>
                    <textarea name="reason_not_achieved" class="s-control" rows="2" placeholder="Jelaskan kenapa target belum tercapai…">{{ old('reason_not_achieved') }}</textarea>
                </div>
                <div style="margin-bottom:16px;">
                    <label class="s-label">Lampiran (opsional)</label>
                    <input type="file" name="attachment" class="s-control" accept="image/jpg,image/jpeg,image/png">
                </div>
                <button type="submit" class="s-btn s-btn-primary">📤 Submit Pencapaian Sore</button>
            </form>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('input[name="is_achieved"]').forEach(r => {
    r.addEventListener('change', () => {
        document.getElementById('reasonWrap').style.display = r.value === '0' ? 'block' : 'none';
    });
});
// init
const checked = document.querySelector('input[name="is_achieved"]:checked');
if (checked) document.getElementById('reasonWrap').style.display = checked.value === '0' ? 'block' : 'none';
</script>
@endpush
