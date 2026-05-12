{{-- resources/views/pages/santri/attendance/index.blade.php --}}
@extends('layouts.Santri')
@section('title','Absensi Santri')

@push('styles')
@include('pages.santri._shared_css')
{{-- <style>
.checkin-card {
    background: linear-gradient(135deg, var(--p) 0%, var(--p-dark) 100%);
    color: #fff; border-radius: var(--radius); padding: 26px 24px;
    margin-bottom: 22px; position: relative; overflow: hidden;
}
.checkin-card::before {
    content:''; position:absolute; top:-40px; right:-40px;
    width:160px; height:160px; border-radius:50%;
    background: rgba(255,255,255,.07);
}
.checkin-status { font-size: .8rem; opacity:.8; margin:0 0 4px; }
.checkin-big    { font-size: 1.3rem; font-weight:800; margin:0 0 18px; }
.checkin-time   { font-size: .85rem; opacity:.75; margin-bottom: 4px; }
.btn-checkin, .btn-checkout {
    display: inline-flex; align-items:center; gap:7px;
    padding: 10px 22px; border-radius: 8px; font-size:.875rem;
    font-weight:700; cursor:pointer; border:none; transition: transform .1s, filter .15s;
}
.btn-checkin  { background:#fff; color:var(--p); }
.btn-checkout { background: rgba(255,255,255,.18); color:#fff; border:2px solid rgba(255,255,255,.5); }
.btn-checkin:hover, .btn-checkout:hover { transform: scale(1.03); }

.history-row { display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid var(--gray-100); }
.history-row:last-child { border-bottom:none; }
.hist-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.hist-dot-in  { background:var(--p); }
.hist-dot-out { background:var(--gold); }
.hist-dot-abs { background:var(--red); }
.hist-date  { font-size:.8rem; color:var(--gray-600); min-width:80px; }
.hist-label { font-size:.875rem; font-weight:600; color:var(--gray-800); flex:1; }
.hist-time  { font-size:.8rem; color:var(--gray-500); }
</style> --}}
@endpush

@section('content')
<div class="container-fluid s-page">

    {{-- Alerts --}}
    @if(session('success'))<div class="s-alert s-alert-success"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="s-alert s-alert-error"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ session('error') }}</div>@endif

    {{-- Check-in Card --}}
    <div class="checkin-card">
        <p class="checkin-status">Status Kehadiran Hari Ini</p>
        @php
            $isCheckedIn  = $status['is_checkin']  ?? false;
            $isCheckedOut = $status['is_checkout'] ?? false;
            $checkInTime  = $status['checkin_time']  ?? null;
            $checkOutTime = $status['checkout_time'] ?? null;
        @endphp
        <p class="checkin-big">
            @if($isCheckedOut) ✅ Sudah Check-Out
            @elseif($isCheckedIn) 🟡 Sudah Check-In
            @else ⭕ Belum Absen
            @endif
        </p>
        <div style="display:flex;gap:18px;margin-bottom:18px;flex-wrap:wrap;">
            @if($checkInTime)<span class="checkin-time">Masuk: <strong>{{ \Carbon\Carbon::parse($checkInTime)->format('H:i') }}</strong></span>@endif
            @if($checkOutTime)<span class="checkin-time">Keluar: <strong>{{ \Carbon\Carbon::parse($checkOutTime)->format('H:i') }}</strong></span>@endif
        </div>
        @if(!$isCheckedOut)
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            @if(!$isCheckedIn)
            <button class="btn-checkin" onclick="openGeoModal('checkin')">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Check-In Sekarang
            </button>
            @else
            <button class="btn-checkout" onclick="openGeoModal('checkout')">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Check-Out Sekarang
            </button>
            @endif
        </div>
        @endif
    </div>

    {{-- Summary Stats --}}
    <div class="s-stats">
        @php
            $statsMap = [
                ['label'=>'Hadir',    'key'=>'hadir',    'color'=>'var(--p)'],
                ['label'=>'Izin',     'key'=>'izin',     'color'=>'var(--gold)'],
                ['label'=>'Sakit',    'key'=>'sakit',    'color'=>'var(--blue)'],
                ['label'=>'Alpha',    'key'=>'alpha',    'color'=>'var(--red)'],
                ['label'=>'Terlambat','key'=>'terlambat','color'=>'#8b5cf6'],
            ];
        @endphp
        @foreach($statsMap as $s)
        <div class="s-stat-card">
            <div class="s-stat-val" style="color:{{ $s['color'] }}">{{ $summary[$s['key']] ?? 0 }}</div>
            <div class="s-stat-label">{{ $s['label'] }}</div>
        </div>
        @endforeach
    </div>

    <div class="row g-4">
        {{-- History --}}
        <div class="col-lg-8">
            <div class="s-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <p class="s-card-title" style="margin:0;">Riwayat Absensi</p>
                    <form method="GET" style="display:flex;gap:8px;align-items:center;">
                        <select name="month" class="s-control" style="width:auto;padding:6px 10px;font-size:.8rem;" onchange="this.form.submit()">
                            @for($m=1;$m<=12;$m++)
                            <option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('M') }}</option>
                            @endfor
                        </select>
                        <select name="year" class="s-control" style="width:auto;padding:6px 10px;font-size:.8rem;" onchange="this.form.submit()">
                            @for($y=now()->year;$y>=now()->year-2;$y--)
                            <option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </form>
                </div>
                @forelse($history as $h)
                @php
                    $sts = $h['status'] ?? 'hadir';
                    $dotClass = match($sts){ 'hadir'=>'hist-dot-in','izin','sakit'=>'hist-dot-out',default=>'hist-dot-abs' };
                @endphp
                <div class="history-row">
                    <span class="hist-dot {{ $dotClass }}"></span>
                    <span class="hist-date">{{ isset($h['date']) ? \Carbon\Carbon::parse($h['date'])->translatedFormat('d M') : '-' }}</span>
                    <span class="hist-label">{{ ucfirst($sts) }}</span>
                    <span class="hist-time">
                        @if(!empty($h['checkin_time']))  Masuk: {{ \Carbon\Carbon::parse($h['checkin_time'])->format('H:i') }} @endif
                        @if(!empty($h['checkout_time'])) &nbsp;·&nbsp; Keluar: {{ \Carbon\Carbon::parse($h['checkout_time'])->format('H:i') }} @endif
                    </span>
                </div>
                @empty
                <div class="s-empty">
                    <div class="s-empty-icon"><svg width="24" height="24" fill="none" stroke="#adb5bd" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                    <p class="s-empty-title">Belum ada riwayat</p>
                    <p class="s-empty-desc">Tidak ada data absensi bulan ini.</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Ringkasan --}}
        <div class="col-lg-4">
            <div class="s-card">
                <p class="s-card-title">Ringkasan Bulan Ini</p>
                @php
                    $percent = isset($summary['persentase_hadir']) ? (float)$summary['persentase_hadir'] : 0;
                    $pColor  = $percent >= 75 ? 'var(--p)' : ($percent >= 50 ? 'var(--gold)' : 'var(--red)');
                @endphp
                <div style="text-align:center;margin-bottom:18px;">
                    <div style="font-size:2.5rem;font-weight:800;color:{{ $pColor }};">{{ number_format($percent,1) }}%</div>
                    <div style="font-size:.8rem;color:var(--gray-500);">Persentase Kehadiran</div>
                </div>
                <div style="background:var(--gray-100);border-radius:999px;height:8px;overflow:hidden;margin-bottom:18px;">
                    <div style="width:{{ min($percent,100) }}%;height:100%;background:{{ $pColor }};border-radius:999px;transition:width .6s;"></div>
                </div>
                @foreach([['Hari Efektif','total_hari'],['Hadir','hadir'],['Izin','izin'],['Sakit','sakit'],['Alpha','alpha']] as [$lbl,$key])
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--gray-100);font-size:.85rem;">
                    <span style="color:var(--gray-600);">{{ $lbl }}</span>
                    <span style="font-weight:700;color:var(--gray-800);">{{ $summary[$key] ?? 0 }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Geolocation Modal --}}
<div id="geoModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:400px;box-shadow:var(--shadow-md);margin:16px;">
        <p style="font-size:1.1rem;font-weight:700;color:var(--gray-900);margin:0 0 6px;" id="geoTitle">Check-In</p>
        <p style="font-size:.85rem;color:var(--gray-600);margin:0 0 20px;" id="geoDesc">Sistem akan mengambil lokasi Anda saat ini.</p>
        <div id="geoStatus" style="display:none;padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="closeGeoModal()" style="padding:9px 18px;background:#fff;border:1.5px solid var(--gray-300);border-radius:8px;font-size:.875rem;font-weight:600;color:var(--gray-700);cursor:pointer;">Batal</button>
            <button id="geoConfirmBtn" onclick="submitGeo()" style="padding:9px 20px;background:var(--p);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:700;cursor:pointer;">📍 Ambil Lokasi & Submit</button>
        </div>
        <form id="geoForm" method="POST" style="display:none;">
            @csrf
            <input type="hidden" name="latitude"  id="geoLat">
            <input type="hidden" name="longitude" id="geoLng">
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
let geoAction = 'checkin';
const routes = {
    checkin:  "{{ route('santri.attendance.checkin') }}",
    checkout: "{{ route('santri.attendance.checkout') }}",
};
function openGeoModal(action) {
    geoAction = action;
    document.getElementById('geoTitle').textContent = action === 'checkin' ? 'Check-In Sekarang' : 'Check-Out Sekarang';
    document.getElementById('geoModal').style.display = 'flex';
    document.getElementById('geoStatus').style.display = 'none';
}
function closeGeoModal() { document.getElementById('geoModal').style.display = 'none'; }
function submitGeo() {
    const statusEl = document.getElementById('geoStatus');
    const btn      = document.getElementById('geoConfirmBtn');
    statusEl.style.display = 'block';
    statusEl.style.background = '#fef3cd';
    statusEl.style.color = '#856404';
    statusEl.textContent = '⏳ Mengambil lokasi GPS…';
    btn.disabled = true;
    navigator.geolocation.getCurrentPosition(
        pos => {
            document.getElementById('geoLat').value = pos.coords.latitude;
            document.getElementById('geoLng').value = pos.coords.longitude;
            const form = document.getElementById('geoForm');
            form.action = routes[geoAction];
            statusEl.style.background = '#d1f0e0'; statusEl.style.color = 'var(--p)';
            statusEl.textContent = `✅ Lokasi didapat (${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)})`;
            setTimeout(() => form.submit(), 600);
        },
        err => {
            statusEl.style.background = '#fde8e8'; statusEl.style.color = 'var(--red)';
            statusEl.textContent = '❌ Gagal: ' + err.message;
            btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}
</script>
@endpush
