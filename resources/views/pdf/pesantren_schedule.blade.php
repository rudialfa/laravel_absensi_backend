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
 
        table { width:100%; border-collapse:collapse; font-size:9.5px; }
        th { background:#e8f5ee; color:#1a6b3c; font-weight:700; padding:5px 7px; text-align:left; border-bottom:1px solid #c3e6cf; }
        td { padding:5px 7px; border-bottom:1px solid #f0f0f0; vertical-align:top; }
        tr:nth-child(even) td { background:#fafafa; }
 
        .badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:8px; font-weight:600; }
        .status-scheduled { background:#cce5ff; color:#004085; }
        .status-ongoing   { background:#d4edda; color:#155724; }
        .status-done      { background:#e9ecef; color:#495057; }
        .status-cancelled { background:#f8d7da; color:#721c24; }
 
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
        <div class="title">Rekap Jadwal Kegiatan</div>
        <div class="sub">Periode: {{ $periodLabel }}</div>
    </div>
    <div class="printed">Dicetak: {{ $generatedAt }}</div>
</div>
 
<table>
    <thead>
        <tr>
            <th style="width:24px;">#</th>
            <th>Judul Jadwal</th>
            <th>Tipe</th>
            <th style="width:90px;">Mulai</th>
            <th style="width:90px;">Selesai</th>
            <th>Lokasi</th>
            <th style="width:60px; text-align:center;">Status</th>
            <th>Dibuat Oleh</th>
            <th style="width:40px; text-align:center;">Peserta</th>
        </tr>
    </thead>
    <tbody>
        @forelse($schedules as $i => $s)
            @php
                $start = \Carbon\Carbon::parse($s->start_datetime);
                $end   = $s->end_datetime ? \Carbon\Carbon::parse($s->end_datetime) : null;
                $status = $s->status ?? 'scheduled';
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-weight:600; max-width:120px; word-wrap:break-word;">{{ $s->title }}</td>
                <td>{{ $s->type ?? '-' }}</td>
                <td>
                    {{ $start->format('d/m/Y') }}<br>
                    <span style="color:#666;">{{ $start->format('H:i') }}</span>
                </td>
                <td>
                    @if($end)
                        {{ $end->format('d/m/Y') }}<br>
                        <span style="color:#666;">{{ $end->format('H:i') }}</span>
                    @else
                        <span style="color:#aaa;">-</span>
                    @endif
                </td>
                <td>{{ $s->location ?? '-' }}</td>
                <td style="text-align:center;">
                    <span class="badge status-{{ $status }}">{{ ucfirst($status) }}</span>
                </td>
                <td>{{ $s->creator?->name ?? $s->user?->name ?? '-' }}</td>
                <td style="text-align:center;">{{ $s->participants?->count() ?? 0 }}</td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center; color:#aaa; font-style:italic; padding:16px;">Tidak ada jadwal.</td></tr>
        @endforelse
    </tbody>
</table>
 
<div class="footer">
    {{ $company->name ?? 'Pesantren' }} &mdash; Rekap Jadwal &mdash; {{ $generatedAt }}
</div>
</body>
</html>