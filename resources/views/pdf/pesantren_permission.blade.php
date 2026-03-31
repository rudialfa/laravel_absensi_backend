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
        .stat-box { flex:1; border:1px solid #ddd; border-radius:5px; padding:6px 8px; text-align:center; }
        .stat-label { font-size:8px; color:#888; text-transform:uppercase; }
        .stat-value { font-size:15px; font-weight:800; margin-top:1px; }
 
        table { width:100%; border-collapse:collapse; font-size:10px; }
        th { background:#e8f5ee; color:#1a6b3c; font-weight:700; padding:5px 7px; text-align:left; border-bottom:1px solid #c3e6cf; }
        td { padding:5px 7px; border-bottom:1px solid #f0f0f0; vertical-align:top; }
        tr:nth-child(even) td { background:#fafafa; }
 
        .badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:8.5px; font-weight:600; }
        .badge-approved { background:#d4edda; color:#155724; }
        .badge-rejected { background:#f8d7da; color:#721c24; }
        .badge-pending  { background:#fff3cd; color:#856404; }
 
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
        <div class="title">Rekap Izin Santri</div>
        <div class="sub">Periode: {{ $periodLabel }}</div>
    </div>
    <div class="printed">Dicetak: {{ $generatedAt }}</div>
</div>
 
<div class="stats-row">
    <div class="stat-box"><div class="stat-label">Total</div><div class="stat-value" style="color:#555;">{{ $stats['total'] }}</div></div>
    <div class="stat-box"><div class="stat-label">Disetujui</div><div class="stat-value" style="color:#1a6b3c;">{{ $stats['approved'] }}</div></div>
    <div class="stat-box"><div class="stat-label">Ditolak</div><div class="stat-value" style="color:#c0392b;">{{ $stats['rejected'] }}</div></div>
    <div class="stat-box"><div class="stat-label">Pending</div><div class="stat-value" style="color:#e6a817;">{{ $stats['pending'] }}</div></div>
</div>
 
<table>
    <thead>
        <tr>
            <th style="width:24px;">#</th>
            <th>Nama Santri</th>
            <th>Kamar / Kelas</th>
            <th style="width:75px;">Tgl Izin</th>
            <th>Alasan</th>
            <th style="width:60px; text-align:center;">Bukti</th>
            <th style="width:65px; text-align:center;">Status</th>
            <th style="width:80px;">Tgl Pengajuan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($permissions as $i => $p)
            @php
                $status = is_null($p->is_approved) ? 'pending' : ($p->is_approved ? 'approved' : 'rejected');
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-weight:600;">{{ $p->user?->name ?? '-' }}</td>
                <td>{{ $p->user?->position ?? '-' }} / {{ $p->user?->department ?? '-' }}</td>
                <td>{{ \Carbon\Carbon::parse($p->date_permission)->format('d/m/Y') }}</td>
                <td style="max-width:140px; word-wrap:break-word;">{{ $p->reason }}</td>
                <td style="text-align:center;">
                    {{ $p->image ? '✓ Ada' : '-' }}
                </td>
                <td style="text-align:center;">
                    <span class="badge badge-{{ $status }}">
                        {{ $status === 'approved' ? 'Disetujui' : ($status === 'rejected' ? 'Ditolak' : 'Pending') }}
                    </span>
                </td>
                <td style="font-size:9px;">{{ \Carbon\Carbon::parse($p->created_at)->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center; color:#aaa; font-style:italic; padding:16px;">Tidak ada data izin.</td></tr>
        @endforelse
    </tbody>
</table>
 
<div class="footer">
    {{ $company->name ?? 'Pesantren' }} &mdash; Rekap Izin Santri &mdash; {{ $generatedAt }}
</div>
</body>
</html>