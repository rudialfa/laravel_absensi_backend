@extends('layouts.employee')

@section('title', 'Absensi')
@section('breadcrumb')
    <span class="current">Absensi</span>
@endsection

@section('content')
<div class="page-title">Absensi Harian</div>
<div class="page-sub">Pantau kehadiran dan lakukan check-in / check-out di sini.</div>

{{-- ── Check-In Card ────────────────────────────────────────────────────── --}}
<div class="checkin-card">
    <div style="flex:1">
        <div class="checkin-date" id="today-date">{{ now()->isoFormat('dddd, D MMMM Y') }}</div>
        <div class="checkin-clock" id="live-clock">--:--:--</div>

        @php
            $checkedIn  = $status['is_checked_in']  ?? false;
            $checkedOut = $status['is_checked_out'] ?? false;
            $checkInTime  = $status['check_in_time']  ?? null;
            $checkOutTime = $status['check_out_time'] ?? null;
        @endphp

        <div style="margin: 10px 0 18px; font-size:13px; opacity:.85">
            @if($checkInTime)
                Masuk: <strong>{{ \Carbon\Carbon::parse($checkInTime)->format('H:i') }}</strong>
            @endif
            @if($checkOutTime)
                &nbsp;·&nbsp; Keluar: <strong>{{ \Carbon\Carbon::parse($checkOutTime)->format('H:i') }}</strong>
            @endif
            @if(!$checkInTime && !$checkOutTime)
                Belum melakukan absensi hari ini.
            @endif
        </div>

        <div class="checkin-actions">
            @if(!$checkedIn)
                <button class="btn btn-white" onclick="openCheckinModal('in')">
                    ↗ Check-In Sekarang
                </button>
            @else
                <span style="background:rgba(255,255,255,.2);padding:6px 14px;border-radius:6px;font-size:13px">
                    ✓ Sudah Check-In {{ $checkInTime ? \Carbon\Carbon::parse($checkInTime)->format('H:i') : '' }}
                </span>
            @endif

            @if($checkedIn && !$checkedOut)
                <button class="btn btn-outline-white" onclick="openCheckinModal('out')">
                    ↙ Check-Out
                </button>
            @elseif($checkedOut)
                <span style="background:rgba(255,255,255,.2);padding:6px 14px;border-radius:6px;font-size:13px">
                    ✓ Sudah Check-Out {{ $checkOutTime ? \Carbon\Carbon::parse($checkOutTime)->format('H:i') : '' }}
                </span>
            @endif
        </div>
    </div>

    {{-- Status icon --}}
    <div style="text-align:center;opacity:.6">
        <div style="font-size:56px">
            @if($checkedOut) ✅
            @elseif($checkedIn) 🕐
            @else 📍
            @endif
        </div>
        <div style="font-size:12px;margin-top:4px">
            @if($checkedOut) Selesai
            @elseif($checkedIn) Sedang hadir
            @else Belum hadir
            @endif
        </div>
    </div>
</div>

{{-- ── Statistik Bulan ─────────────────────────────────────────────────── --}}
<div class="metrics">
    <div class="metric">
        <div class="metric-label">Hadir</div>
        <div class="metric-val success">{{ $summary['present'] ?? 0 }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Terlambat</div>
        <div class="metric-val warning">{{ $summary['late'] ?? 0 }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Izin</div>
        <div class="metric-val info">{{ $summary['permitted'] ?? 0 }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Cuti</div>
        <div class="metric-val info">{{ $summary['on_leave'] ?? 0 }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Alpha</div>
        <div class="metric-val danger">{{ $summary['absent'] ?? 0 }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Kehadiran</div>
        <div class="metric-val primary">{{ $summary['attendance_rate'] ?? '0' }}%</div>
    </div>
</div>

{{-- ── Filter ──────────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Riwayat Absensi</span>
        <form method="GET" action="{{ route('employee.attendance.index') }}" style="display:flex;gap:8px">
            <select name="month" class="btn" style="padding:6px 10px;width:auto">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create(null, $m)->isoFormat('MMMM') }}
                    </option>
                @endforeach
            </select>
            <select name="year" class="btn" style="padding:6px 10px;width:auto">
                @foreach([now()->year, now()->year - 1] as $y)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Shift</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Terlambat</th>
                    <th>Pulang Awal</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history['data'] ?? $history as $row)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($row['date'] ?? $row['attendance_date'])->isoFormat('ddd, D MMM') }}</td>
                        <td>{{ $row['shift_name'] ?? $row['shift']['name'] ?? '—' }}</td>
                        <td style="font-family:'DM Mono',monospace">
                            {{ $row['check_in_time'] ? \Carbon\Carbon::parse($row['check_in_time'])->format('H:i') : '—' }}
                        </td>
                        <td style="font-family:'DM Mono',monospace">
                            {{ $row['check_out_time'] ? \Carbon\Carbon::parse($row['check_out_time'])->format('H:i') : '—' }}
                        </td>
                        <td>{{ $row['late_minutes'] ? $row['late_minutes'].' mnt' : '—' }}</td>
                        <td>{{ $row['early_leave_minutes'] ? $row['early_leave_minutes'].' mnt' : '—' }}</td>
                        <td>
                            @php $s = $row['status'] ?? 'unknown'; @endphp
                            @if($s === 'on_time') <span class="badge badge-success">Tepat Waktu</span>
                            @elseif($s === 'late') <span class="badge badge-warning">Terlambat</span>
                            @elseif($s === 'absent') <span class="badge badge-danger">Alpha</span>
                            @elseif($s === 'on_leave') <span class="badge badge-info">Cuti</span>
                            @elseif($s === 'permitted') <span class="badge badge-info">Izin</span>
                            @else <span class="badge badge-gray">{{ $s }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">
                        <div class="empty"><div class="empty-icon">📅</div><div class="empty-text">Tidak ada data absensi untuk periode ini.</div></div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if(isset($history['last_page']) && $history['last_page'] > 1)
        <div class="pagination" style="margin-top:16px">
            @for($p = 1; $p <= $history['last_page']; $p++)
                <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}"
                   class="{{ $p == ($history['current_page'] ?? 1) ? 'active' : '' }}">{{ $p }}</a>
            @endfor
        </div>
    @endif
</div>

{{-- ── Modal Check-In / Check-Out ─────────────────────────────────────── --}}
<div id="checkin-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.45);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:var(--radius-lg);padding:28px;width:420px;max-width:90vw;box-shadow:var(--shadow-md)">
        <div style="font-size:16px;font-weight:600;margin-bottom:6px" id="modal-title">Check-In</div>
        <div style="font-size:13px;color:var(--c-muted);margin-bottom:20px">Pastikan kamu sudah berada di lokasi kerja.</div>

        <form id="checkin-form" method="POST" action="">
            @csrf
            <input type="hidden" name="latitude"  id="modal-lat">
            <input type="hidden" name="longitude" id="modal-lng">

            <div id="location-status" style="padding:12px;background:var(--c-bg);border-radius:var(--radius-sm);font-size:13px;margin-bottom:16px;color:var(--c-muted)">
                📍 Mengambil lokasi GPS...
            </div>

            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary" style="flex:1" id="modal-submit-btn" disabled>
                    Konfirmasi
                </button>
                <button type="button" class="btn" onclick="closeCheckinModal()">Batal</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Live clock
function updateClock() {
    const now = new Date();
    document.getElementById('live-clock').textContent = now.toLocaleTimeString('id-ID');
}
setInterval(updateClock, 1000);
updateClock();

function openCheckinModal(type) {
    const modal = document.getElementById('checkin-modal');
    const form  = document.getElementById('checkin-form');
    const title = document.getElementById('modal-title');
    const btn   = document.getElementById('modal-submit-btn');
    const status = document.getElementById('location-status');

    title.textContent = type === 'in' ? 'Konfirmasi Check-In' : 'Konfirmasi Check-Out';
    form.action = type === 'in'
        ? '{{ route('employee.attendance.checkin') }}'
        : '{{ route('employee.attendance.checkout') }}';

    modal.style.display = 'flex';
    btn.disabled = true;
    status.textContent = '📍 Mengambil lokasi GPS...';

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => {
                document.getElementById('modal-lat').value = pos.coords.latitude;
                document.getElementById('modal-lng').value = pos.coords.longitude;
                status.textContent = `✅ Lokasi ditemukan: ${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)}`;
                status.style.color = 'var(--c-success)';
                btn.disabled = false;
            },
            () => {
                status.textContent = '⚠ Gagal mengambil lokasi. Pastikan GPS aktif.';
                status.style.color = 'var(--c-danger)';
            }
        );
    } else {
        status.textContent = '⚠ Browser tidak mendukung geolocation.';
        status.style.color = 'var(--c-danger)';
    }
}

function closeCheckinModal() {
    document.getElementById('checkin-modal').style.display = 'none';
}
</script>
@endpush