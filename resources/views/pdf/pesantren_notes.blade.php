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
        .stat-box { flex:1; border-radius:5px; padding:7px 10px; text-align:center; }
        .stat-label { font-size:8px; color:#fff; opacity:.85; text-transform:uppercase; }
        .stat-value { font-size:16px; font-weight:800; color:#fff; margin-top:2px; }
 
        table { width:100%; border-collapse:collapse; font-size:9.5px; }
        th { background:#e8f5ee; color:#1a6b3c; font-weight:700; padding:5px 7px; text-align:left; border-bottom:1px solid #c3e6cf; }
        td { padding:5px 7px; border-bottom:1px solid #f0f0f0; vertical-align:top; }
        tr:nth-child(even) td { background:#fafafa; }
 
        .type-warning     { background:#f8d7da; color:#721c24; }
        .type-praise      { background:#d4edda; color:#155724; }
        .type-performance { background:#cce5ff; color:#004085; }
        .type-absence     { background:#fff3cd; color:#856404; }
        .type-general     { background:#e9ecef; color:#495057; }
        .badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:8px; font-weight:600; }
        .read-yes { color:#1a6b3c; } .read-no { color:#c0392b; font-weight:700; }
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
        <div class="title">Rekap Catatan Santri</div>
        <div class="sub">Periode: {{ $periodLabel }}</div>
    </div>
    <div class="printed">Dicetak: {{ $generatedAt }}</div>
</div>
 
<div class="stats-row">
    <div class="stat-box" style="background:#555;">
        <div class="stat-label">Total</div><div class="stat-value">{{ $stats['total'] }}</div>
    </div>
    <div class="stat-box" style="background:#c0392b;">
        <div class="stat-label">Warning</div><div class="stat-value">{{ $stats['warning'] }}</div>
    </div>
    <div class="stat-box" style="background:#1a6b3c;">
        <div class="stat-label">Pujian</div><div class="stat-value">{{ $stats['praise'] }}</div>
    </div>
    <div class="stat-box" style="background:#0066cc;">
        <div class="stat-label">Performa</div><div class="stat-value">{{ $stats['performance'] }}</div>
    </div>
    <div class="stat-box" style="background:#e6a817;">
        <div class="stat-label">Ketidakhadiran</div><div class="stat-value">{{ $stats['absence'] }}</div>
    </div>
    <div class="stat-box" style="background:#888;">
        <div class="stat-label">Umum</div><div class="stat-value">{{ $stats['general'] }}</div>
    </div>
    <div class="stat-box" style="background:#c0392b;">
        <div class="stat-label">Belum Dibaca</div><div class="stat-value">{{ $stats['unread'] }}</div>
    </div>
</div>
 
<table>
    <thead>
        <tr>
            <th style="width:24px;">#</th>
            <th>Santri</th>
            <th>Kamar / Kelas</th>
            <th style="width:70px;">Tanggal</th>
            <th style="width:70px; text-align:center;">Tipe</th>
            <th>Judul & Isi Catatan</th>
            <th>Target / Alasan</th>
            <th>Dicatat Oleh</th>
            <th style="width:45px; text-align:center;">Dibaca</th>
        </tr>
    </thead>
    <tbody>
        @forelse($notes as $i => $note)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-weight:600;">{{ $note->user?->name ?? '-' }}</td>
                <td>{{ $note->user?->position ?? '-' }} / {{ $note->user?->department ?? '-' }}</td>
                <td>{{ \Carbon\Carbon::parse($note->created_at)->format('d/m/Y') }}</td>
                <td style="text-align:center;">
                    <span class="badge type-{{ $note->type }}">{{ ucfirst($note->type) }}</span>
                </td>
                <td style="max-width:130px; word-wrap:break-word;">
                    <strong>{{ $note->title }}</strong><br>
                    <span style="color:#555; font-size:9px;">{{ Str::limit($note->note, 80) }}</span>
                </td>
                <td style="max-width:90px; word-wrap:break-word; font-size:9px; color:#555;">
                    @if($note->target_achievement)<b>Target:</b> {{ Str::limit($note->target_achievement, 50) }}<br>@endif
                    @if($note->reason)<b>Alasan:</b> {{ Str::limit($note->reason, 50) }}@endif
                </td>
                <td>{{ $note->creator?->name ?? '-' }}</td>
                <td style="text-align:center;">
                    @if($note->is_read)
                        <span class="read-yes">✓ Ya</span>
                    @else
                        <span class="read-no">✗ Belum</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center; color:#aaa; font-style:italic; padding:16px;">Tidak ada catatan.</td></tr>
        @endforelse
    </tbody>
</table>
 
<div class="footer">
    {{ $company->name ?? 'Pesantren' }} &mdash; Rekap Catatan Santri &mdash; {{ $generatedAt }}
</div>
</body>
</html>