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
        td { padding:5px 7px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        tr:nth-child(even) td { background:#fafafa; }
 
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
        <div class="title">Daftar Santri</div>
        <div class="sub">Total: {{ is_object($santriList) && method_exists($santriList, 'total') ? $santriList->total() : count($santriList) }} santri</div>
    </div>
    <div class="printed">Dicetak: {{ $generatedAt }}</div>
</div>
 
<table>
    <thead>
        <tr>
            <th style="width:28px;">#</th>
            <th>Nama Santri</th>
            <th>Email</th>
            <th>No. HP</th>
            <th>Kamar (Posisi)</th>
            <th>Kelas / Angkatan</th>
        </tr>
    </thead>
    <tbody>
        @php $no = 1; @endphp
        @forelse($santriList as $s)
            <tr>
                <td>{{ $no++ }}</td>
                <td style="font-weight:600;">{{ $s->name }}</td>
                <td>{{ $s->email ?? '-' }}</td>
                <td>{{ $s->phone ?? '-' }}</td>
                <td>{{ $s->position ?? '-' }}</td>
                <td>{{ $s->department ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center; color:#aaa; font-style:italic; padding:16px;">Tidak ada data santri.</td></tr>
        @endforelse
    </tbody>
</table>
 
<div class="footer">
    {{ $company->name ?? 'Pesantren' }} &mdash; Daftar Santri &mdash; {{ $generatedAt }}
</div>
</body>
</html>