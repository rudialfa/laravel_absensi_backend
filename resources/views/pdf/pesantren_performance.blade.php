<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:sans-serif; font-size:10px; color:#222; padding:20px 24px; }
        .header { border-bottom:2px solid #1a6b3c; padding-bottom:10px; margin-bottom:14px; display:flex; align-items:center; gap:14px; }
        .logo { height:48px; } .company-name { font-size:14px; font-weight:700; color:#1a6b3c; }
        .title { font-size:17px; font-weight:800; color:#111; margin-top:2px; }
        .sub { font-size:10px; color:#666; } .printed { margin-left:auto; font-size:9px; color:#aaa; text-align:right; }
 
        .stats-row { display:flex; gap:8px; margin-bottom:14px; }
        .stat-box { flex:1; border:1px solid #ddd; border-radius:5px; padding:7px 10px; text-align:center; }
        .stat-label { font-size:8px; color:#888; text-transform:uppercase; }
        .stat-value { font-size:16px; font-weight:800; margin-top:2px; }
 
        table { width:100%; border-collapse:collapse; font-size:10px; }
        th { background:#e8f5ee; color:#1a6b3c; font-weight:700; padding:5px 7px; text-align:left; border-bottom:1px solid #c3e6cf; }
        td { padding:5px 7px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        tr:nth-child(even) td { background:#fafafa; }
 
        .rank-1 { background:#fff3cd; font-weight:800; color:#856404; }
        .rank-2 { background:#f0f0f0; font-weight:700; color:#444; }
        .rank-3 { background:#fff0e6; font-weight:700; color:#7a3f00; }
 
        .score-bar-wrap { background:#eee; border-radius:4px; height:8px; width:100%; }
        .score-bar { height:8px; border-radius:4px; }
 
        .footer { margin-top:16px; border-top:1px solid #eee; padding-top:6px; font-size:8px; color:#aaa; text-align:center; }
    </style>
</head>
<body>
<div class="header">
    @if(!empty($company->image_url))
        <img class="logo" src="{{ public_path($company->image_url) }}" alt="Logo">
    @endif
    <div>
        <div class="company-name">{{ $company->name ?? 'Pesantren' }}</div>
        <div class="title">Rekap Nilai Performa Santri</div>
        <div class="sub">Periode: {{ $periodLabel }}</div>
    </div>
    <div class="printed">Dicetak: {{ $generatedAt }}</div>
</div>
 
<div class="stats-row">
    <div class="stat-box"><div class="stat-label">Total Santri</div><div class="stat-value" style="color:#555;">{{ $stats['total'] }}</div></div>
    <div class="stat-box"><div class="stat-label">Rata-rata</div><div class="stat-value" style="color:#1a6b3c;">{{ $stats['avg_score'] }}</div></div>
    <div class="stat-box"><div class="stat-label">Tertinggi</div><div class="stat-value" style="color:#1a6b3c;">{{ $stats['max_score'] }}</div></div>
    <div class="stat-box"><div class="stat-label">Terendah</div><div class="stat-value" style="color:#c0392b;">{{ $stats['min_score'] }}</div></div>
    <div class="stat-box"><div class="stat-label">Skor ≥ 80</div><div class="stat-value" style="color:#1a6b3c;">{{ $stats['above_80'] }}</div></div>
    <div class="stat-box"><div class="stat-label">Skor &lt; 50</div><div class="stat-value" style="color:#c0392b;">{{ $stats['below_50'] }}</div></div>
</div>
 
<table>
    <thead>
        <tr>
            <th style="width:36px; text-align:center;">Rank</th>
            <th>Nama Santri</th>
            <th>Kamar</th>
            <th>Kelas</th>
            <th style="width:50px; text-align:center;">Total Target</th>
            <th style="width:55px; text-align:center;">Tercapai</th>
            <th style="width:55px; text-align:center;">Achievement</th>
            <th style="width:60px; text-align:center;">Nilai Akhir</th>
            <th style="width:80px;">Grafik</th>
        </tr>
    </thead>
    <tbody>
        @forelse($scores as $s)
            @php
                $rank = $s->rank ?? ($loop->index + 1);
                $rowClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : ''));
                $score = round($s->final_score ?? 0, 1);
                $barColor = $score >= 80 ? '#1a6b3c' : ($score >= 60 ? '#e6a817' : '#c0392b');
                $barWidth = min(100, $score);
            @endphp
            <tr class="{{ $rowClass }}">
                <td style="text-align:center; font-weight:800; font-size:12px;">
                    @if($rank === 1) 🥇
                    @elseif($rank === 2) 🥈
                    @elseif($rank === 3) 🥉
                    @else {{ $rank }}
                    @endif
                </td>
                <td style="font-weight:600;">{{ $s->user?->name ?? '-' }}</td>
                <td>{{ $s->user?->position ?? '-' }}</td>
                <td>{{ $s->user?->department ?? '-' }}</td>
                <td style="text-align:center;">{{ $s->total_targets }}</td>
                <td style="text-align:center;">{{ $s->targets_achieved }}</td>
                <td style="text-align:center; color:{{ $barColor }}; font-weight:700;">
                    {{ number_format($s->achievement_rate ?? 0, 1) }}%
                </td>
                <td style="text-align:center; font-weight:800; font-size:14px; color:{{ $barColor }};">
                    {{ $score }}
                </td>
                <td>
                    <div class="score-bar-wrap">
                        <div class="score-bar" style="width:{{ $barWidth }}%; background:{{ $barColor }};"></div>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center; color:#aaa; font-style:italic; padding:16px;">Tidak ada data performa.</td></tr>
        @endforelse
    </tbody>
</table>
 
<div class="footer">
    {{ $company->name ?? 'Pesantren' }} &mdash; Rekap Nilai Performa &mdash; {{ $generatedAt }}
</div>
</body>
</html>