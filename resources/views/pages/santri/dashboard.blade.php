@extends('layouts.santri')
@section('title', 'Dashboard')
@section('breadcrumb')<span class="current">Dashboard</span>@endsection

@section('content')
@php
    $santri        = $dashboard['santri']           ?? [];
    $absensi       = $dashboard['absensi_hari_ini'] ?? [];
    $stats         = $dashboard['statistik_bulan_ini'] ?? [];
    $laporan       = $dashboard['laporan_harian']   ?? [];
    $mutabaah      = $dashboard['mutabaah_hari_ini'] ?? [];
    $progressNgaji = $dashboard['progress_ngaji']   ?? null;
    $skorBulan     = $dashboard['skor_bulan_ini']   ?? null;
    $prayerToday   = $prayer;
    $nextAction    = $absensi['next_action'] ?? 'checkin';
@endphp

{{-- ── Selamat datang + Absensi ─────────────────────────────── --}}
<div class="checkin-card">
    <div style="flex:1">
        <div class="checkin-date">{{ now()->isoFormat('dddd, D MMMM Y') }}</div>
        <div class="checkin-clock" id="live-clock">--:--:--</div>
        <div style="font-size:13px;margin:8px 0 16px;opacity:.8">
            Assalamu'alaikum, <strong>{{ $santri['name'] ?? auth()->user()->name }}</strong>
            @if($absensi['time_in']??null) · Masuk: <strong>{{ substr($absensi['time_in'],0,5) }}</strong>@endif
            @if($absensi['time_out']??null) · Pulang: <strong>{{ substr($absensi['time_out'],0,5) }}</strong>@endif
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            @if($nextAction === 'checkin')
                <button class="btn btn-white" onclick="openModal('in')">✔ Check-In</button>
            @elseif($nextAction === 'checkout')
                <span style="opacity:.75;font-size:13px">✓ Sudah Check-In {{ substr($absensi['time_in']??'',0,5) }}</span>
                <button class="btn btn-white-outline" onclick="openModal('out')">↙ Check-Out</button>
            @else
                <span style="background:rgba(255,255,255,.2);padding:6px 14px;border-radius:6px;font-size:13px">
                    ✓ Absensi Selesai
                </span>
            @endif
        </div>
    </div>
    <div style="text-align:center;opacity:.6">
        <div style="font-size:52px">{{ $nextAction==='done' ? '✅' : ($nextAction==='checkout' ? '🕐' : '🕌') }}</div>
        <div style="font-size:12px;margin-top:4px">
            @if($nextAction==='done') Selesai @elseif($nextAction==='checkout') Sedang hadir @else Belum absen @endif
        </div>
    </div>
</div>

{{-- ── Statistik Bulan ─────────────────────────────────────── --}}
<div class="metrics">
    <div class="metric"><div class="metric-label">Hadir</div><div class="metric-val primary">{{ $stats['hadir'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Terlambat</div><div class="metric-val warning">{{ $stats['terlambat'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Alpha</div><div class="metric-val danger">{{ $stats['alpha'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Izin Pending</div><div class="metric-val accent">{{ $stats['izin_pending'] ?? 0 }}</div></div>
    @if($skorBulan)
        <div class="metric"><div class="metric-label">Skor Bulan</div><div class="metric-val primary">{{ $skorBulan['final_score'] }}</div></div>
    @endif
</div>

<div class="two-col">
    <div>
        {{-- ── Waktu Sholat Hari Ini ─────────────────────────── --}}
        @if($prayerToday)
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <span class="card-title">🕌 Jadwal Sholat Hari Ini</span>
                <a href="{{ route('santri.prayer.today') }}" class="btn btn-sm">Selengkapnya</a>
            </div>
            @php
                $prayers = [
                    'fajr'    => ['Subuh',   $prayerToday['waktu']['fajr']    ?? null],
                    'dzuhur'  => ['Dzuhur',  $prayerToday['waktu']['dzuhur']  ?? null],
                    'ashar'   => ['Ashar',   $prayerToday['waktu']['ashar']   ?? null],
                    'maghrib' => ['Maghrib', $prayerToday['waktu']['maghrib'] ?? null],
                    'isya'    => ['Isya',    $prayerToday['waktu']['isya']    ?? null],
                ];
                $berikutnya = $prayerToday['berikutnya'] ?? null;
            @endphp
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px">
                @foreach($prayers as $key => [$nama, $waktu])
                    <div class="prayer-card {{ $berikutnya === $key ? 'active' : '' }}">
                        <div class="prayer-name">{{ $nama }}</div>
                        <div class="prayer-time">{{ $waktu ?? '--:--' }}</div>
                        @if($berikutnya === $key)<div style="font-size:9px;color:rgba(255,255,255,.7);margin-top:2px">▶ Berikutnya</div>@endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Laporan Harian Hari Ini ───────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">📋 Laporan Harian</span>
                <a href="{{ route('santri.daily-report.today') }}" class="btn btn-sm btn-primary">Isi Laporan</a>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div style="padding:12px;border-radius:var(--radius-md);border:1px solid var(--c-border);text-align:center">
                    <div style="font-size:24px;margin-bottom:4px">{{ ($laporan['submitted_morning'] ?? false) ? '✅' : '⬜' }}</div>
                    <div style="font-size:12px;font-weight:600">Target Pagi</div>
                    <div style="font-size:11px;color:var(--c-muted)">{{ ($laporan['submitted_morning'] ?? false) ? 'Sudah diisi' : 'Belum diisi' }}</div>
                </div>
                <div style="padding:12px;border-radius:var(--radius-md);border:1px solid var(--c-border);text-align:center">
                    <div style="font-size:24px;margin-bottom:4px">{{ ($laporan['submitted_evening'] ?? false) ? '✅' : '⬜' }}</div>
                    <div style="font-size:12px;font-weight:600">Pencapaian Sore</div>
                    <div style="font-size:11px;color:var(--c-muted)">{{ ($laporan['submitted_evening'] ?? false) ? 'Sudah diisi' : 'Belum diisi' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div>
        {{-- ── Progress Ngaji ────────────────────────────────── --}}
        @if($progressNgaji)
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <span class="card-title">📖 Progress Ngaji</span>
                <a href="{{ route('santri.mutabaah.progress') }}" class="btn btn-sm">Detail</a>
            </div>
            <div style="background:var(--c-primary-lt);border-radius:var(--radius-md);padding:14px;border:1px solid var(--c-primary-bd)">
                <div style="font-size:12px;color:var(--c-primary);font-weight:600;margin-bottom:4px">
                    {{ strtoupper($progressNgaji['kitab']) }}
                    @if($progressNgaji['jilid']) — Jilid {{ $progressNgaji['jilid'] }} @endif
                </div>
                <div style="font-size:18px;font-weight:700;color:var(--c-primary)">{{ $progressNgaji['halaman_terakhir'] ?? '—' }}</div>
                @if($progressNgaji['keterangan'])
                    <div style="margin-top:6px">
                        @php
                            $w = $progressNgaji['warna'] ?? 'abu';
                            $cls = ['hijau'=>'badge-hijau','merah'=>'badge-merah','kuning'=>'badge-kuning'][$w] ?? 'badge-gray';
                        @endphp
                        <span class="badge {{ $cls }}">{{ ucfirst($progressNgaji['keterangan']) }}</span>
                    </div>
                @endif
                <div style="font-size:11px;color:var(--c-muted);margin-top:6px">
                    Terakhir: {{ \Carbon\Carbon::parse($progressNgaji['tanggal_terakhir'])->isoFormat('D MMM Y') }}
                </div>
            </div>
        </div>
        @endif

        {{-- ── Mutabaah Hari Ini ─────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">📜 Mutabaah Hari Ini</span>
                <a href="{{ route('santri.mutabaah.index') }}" class="btn btn-sm">Riwayat</a>
            </div>
            @php $sesiList = $mutabaah['sesi'] ?? []; @endphp
            @if(count($sesiList))
                @foreach($sesiList as $sesi)
                    <div class="mutabaah-sesi">
                        <div class="mutabaah-icon">{{ $sesi['sesi'] === 'pagi' ? '🌅' : '🌆' }}</div>
                        <div style="flex:1">
                            <div style="font-size:13px;font-weight:600">{{ ucfirst($sesi['sesi']) }} — {{ $sesi['label_posisi'] ?? '—' }}</div>
                            <div style="font-size:12px;color:var(--c-muted)">Ustadz: {{ $sesi['ustadz'] ?? '—' }}</div>
                        </div>
                        @php $wc = ['hijau'=>'badge-hijau','merah'=>'badge-merah','kuning'=>'badge-kuning'][$sesi['warna']??''] ?? 'badge-gray'; @endphp
                        <span class="badge {{ $wc }}">{{ ucfirst($sesi['keterangan'] ?? '—') }}</span>
                    </div>
                @endforeach
            @else
                <div class="empty" style="padding:20px"><div class="empty-icon">📜</div><div class="empty-text">Belum ada sesi ngaji hari ini.</div></div>
            @endif
        </div>
    </div>
</div>

{{-- ── Modal Absensi ─────────────────────────────────────────── --}}
<div id="absensi-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:var(--radius-lg);padding:28px;width:400px;max-width:92vw;box-shadow:var(--shadow-md)">
        <div style="font-size:16px;font-weight:700;margin-bottom:6px;color:var(--c-primary)" id="modal-title">Check-In</div>
        <div style="font-size:13px;color:var(--c-muted);margin-bottom:18px">Pastikan kamu berada di dalam area pesantren.</div>
        <form id="modal-form" method="POST" action="">
            @csrf
            <input type="hidden" name="latitude" id="mlat">
            <input type="hidden" name="longitude" id="mlng">
            <div id="loc-status" style="padding:12px;background:var(--c-bg);border-radius:var(--radius-sm);font-size:13px;margin-bottom:16px;color:var(--c-muted)">
                📍 Mengambil lokasi GPS...
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary" style="flex:1" id="modal-btn" disabled>Konfirmasi</button>
                <button type="button" class="btn" onclick="closeModal()">Batal</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
setInterval(() => {
    document.getElementById('live-clock').textContent = new Date().toLocaleTimeString('id-ID');
}, 1000);
document.getElementById('live-clock').textContent = new Date().toLocaleTimeString('id-ID');

function openModal(type) {
    const m = document.getElementById('absensi-modal');
    document.getElementById('modal-title').textContent = type === 'in' ? 'Konfirmasi Check-In' : 'Konfirmasi Check-Out';
    document.getElementById('modal-form').action = type === 'in'
        ? '{{ route('santri.attendance.checkin') }}'
        : '{{ route('santri.attendance.checkout') }}';
    document.getElementById('modal-btn').disabled = true;
    document.getElementById('loc-status').textContent = '📍 Mengambil lokasi GPS...';
    document.getElementById('loc-status').style.color = 'var(--c-muted)';
    m.style.display = 'flex';
    navigator.geolocation?.getCurrentPosition(
        p => {
            document.getElementById('mlat').value = p.coords.latitude;
            document.getElementById('mlng').value = p.coords.longitude;
            document.getElementById('loc-status').textContent = `✅ Lokasi: ${p.coords.latitude.toFixed(5)}, ${p.coords.longitude.toFixed(5)}`;
            document.getElementById('loc-status').style.color = 'var(--c-success)';
            document.getElementById('modal-btn').disabled = false;
        },
        () => {
            document.getElementById('loc-status').textContent = '⚠ Gagal ambil lokasi. Aktifkan GPS.';
            document.getElementById('loc-status').style.color = 'var(--c-danger)';
        }
    );
}
function closeModal() { document.getElementById('absensi-modal').style.display = 'none'; }
</script>
@endpush