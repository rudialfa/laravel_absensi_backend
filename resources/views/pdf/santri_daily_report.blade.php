<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:sans-serif; font-size:10px; color:#222; padding:20px 24px; }
        .header { border-bottom:2px solid #1a6b3c; padding-bottom:10px; margin-bottom:14px; display:flex; align-items:center; gap:14px; }
        .company-name { font-size:14px; font-weight:700; color:#1a6b3c; }
        .title { font-size:17px; font-weight:800; color:#111; margin-top:2px; }
        .sub { font-size:10px; color:#666; } .printed { margin-left:auto; font-size:9px; color:#aaa; text-align:right; }
 
        .santri-card { background:#f0faf4; border:1px solid #c3e6cf; border-radius:6px; padding:10px 14px; margin-bottom:12px; display:flex; gap:20px; }
        .sc-label { font-size:8px; color:#888; text-transform:uppercase; }
        .sc-value { font-size:13px; font-weight:700; color:#1a6b3c; margin-top:2px; }
 
        .stats-row { display:flex; gap:8px; margin-bottom:14px; }
        .stat-box { flex:1; border:1px solid #ddd; border-radius:5px; padding:7px 10px; text-align:center; }
        .stat-label { font-size:8px; color:#888; text-transform:uppercase; }
        .stat-value { font-size:15px; font-weight:800; margin-top:2px; }
 
        .rate-bar-wrap { background:#eee; border-radius:4px; height:10px; margin-top:4px; }
        .rate-bar { height:10px; border-radius:4px; }
 
        table { width:100%; border-collapse:collapse; font-size:10px; }
        th { background:#e8f5ee; color:#1a6b3c; font-weight:700; padding:5px 7px; text-align:left; border-bottom:1px solid #c3e6cf; }
        td { padding:5px 7px; border-bottom:1px solid #f0f0f0; vertical-align:top; }
        tr:nth-child(even) td { background:#fafafa; }
 
        .badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:8px; font-weight:600; }
        .badge-green  { background:#d4edda; color:#155724; }
        .badge-red    { background:#f8d7da; color:#721c24; }
        .badge-yellow { background:#fff3cd; color:#856404; }
        .badge-grey   { background:#e9ecef; color:#495057; }
 
        .footer { margin-top:16px; border-top:1px solid #eee; padding-top:6px; font-size:8px; color:#aaa; text-align:center; }
    </style>
</head>
<body>
<div class="header">
    <div>
        <div class="company-name">Laporan Harian Santri</div>
        <div class="title">{{ $santri->name }}</div>
        <div class="sub">Periode: {{ $periodLabel }}</div>
    </div>
    <div class="printed">Dicetak: {{ $generatedAt }}</div>
</div>
 
{{-- INFO SANTRI --}}
<div class="santri-card">
    <div>
        <div class="sc-label">Nama</div>
        <div class="sc-value" style="font-size:14px;">{{ $santri->name }}</div>
    </div>
    <div>
        <div class="sc-label">Kamar</div>
        <div class="sc-value">{{ $santri->position ?? '-' }}</div>
    </div>
    <div>
        <div class="sc-label">Kelas / Angkatan</div>
        <div class="sc-value">{{ $santri->department ?? '-' }}</div>
    </div>
    <div>
        <div class="sc-label">Email</div>
        <div class="sc-value" style="font-size:11px; color:#555;">{{ $santri->email ?? '-' }}</div>
    </div>
</div>
 
{{-- STATISTIK --}}
@php $rateColor = $achievementRate >= 80 ? '#1a6b3c' : ($achievementRate >= 50 ? '#e6a817' : '#c0392b'); @endphp
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-label">Total Hari</div>
        <div class="stat-value" style="color:#555;">{{ $stats['total'] }}</div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Tercapai</div>
        <div class="stat-value" style="color:#1a6b3c;">{{ $stats['achieved'] }}</div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Tidak Tercapai</div>
        <div class="stat-value" style="color:#c0392b;">{{ $stats['not_achieved'] }}</div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Belum Diisi</div>
        <div class="stat-value" style="color:#e6a817;">{{ $stats['pending_evening'] }}</div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Achievement Rate</div>
        <div class="stat-value" style="color:{{ $rateColor }};">{{ $achievementRate }}%</div>
        <div class="rate-bar-wrap">
            <div class="rate-bar" style="width:{{ $achievementRate }}%; background:{{ $rateColor }};"></div>
        </div>
    </div>
</div>
 
<table>
    <thead>
        <tr>
            <th style="width:24px;">#</th>
            <th style="width:75px;">Tanggal</th>
            <th>Target Pagi</th>
            <th>Pencapaian Sore</th>
            <th>Alasan Tidak Tercapai</th>
            <th style="width:65px; text-align:center;">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($reports as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
                <td style="max-width:120px; word-wrap:break-word;">{{ $r->target }}</td>
                <td style="max-width:120px; word-wrap:break-word;">
                    {{ $r->achievement ?? '' }}
                    @if(is_null($r->achievement))
                        <span style="color:#aaa; font-style:italic;">Belum diisi</span>
                    @endif
                </td>
                <td style="font-size:9px; color:#888; max-width:100px; word-wrap:break-word;">
                    {{ $r->reason_not_achieved ?? '-' }}
                </td>
                <td style="text-align:center;">
                    @if(is_null($r->achievement))
                        <span class="badge badge-yellow">Pending</span>
                    @elseif($r->is_achieved)
                        <span class="badge badge-green">✓ Tercapai</span>
                    @else
                        <span class="badge badge-red">✗ Tidak</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center; color:#aaa; font-style:italic; padding:16px;">Tidak ada data laporan harian.</td></tr>
        @endforelse
    </tbody>
</table>
 
<div class="footer">
    Laporan Harian &mdash; {{ $santri->name }} &mdash; {{ $periodLabel }} &mdash; {{ $generatedAt }}
</div>
</body>
</html>