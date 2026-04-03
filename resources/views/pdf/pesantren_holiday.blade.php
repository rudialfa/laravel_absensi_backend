<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: sans-serif;
            font-size: 10px;
            color: #222;
            padding: 20px 24px;
        }

        .header {
            border-bottom: 2px solid #1a6b3c;
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo {
            height: 48px;
        }

        .company-name {
            font-size: 14px;
            font-weight: 700;
            color: #1a6b3c;
        }

        .title {
            font-size: 17px;
            font-weight: 800;
            color: #111;
            margin-top: 2px;
        }

        .sub {
            font-size: 10px;
            color: #666;
        }

        .printed {
            margin-left: auto;
            font-size: 9px;
            color: #aaa;
            text-align: right;
        }

        .stats-row {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }

        .stat-box {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 7px 10px;
            text-align: center;
        }

        .stat-label {
            font-size: 8px;
            color: #888;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 800;
            margin-top: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        th {
            background: #e8f5ee;
            color: #1a6b3c;
            font-weight: 700;
            padding: 5px 7px;
            text-align: left;
            border-bottom: 1px solid #c3e6cf;
        }

        td {
            padding: 5px 7px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        tr:nth-child(even) td {
            background: #fafafa;
        }

        .type-company {
            background: #d4edda;
            color: #155724;
        }

        .type-national {
            background: #cce5ff;
            color: #004085;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8.5px;
            font-weight: 600;
        }

        .active-today {
            color: #1a6b3c;
            font-weight: 700;
        }

        .footer {
            margin-top: 16px;
            border-top: 1px solid #eee;
            padding-top: 6px;
            font-size: 8px;
            color: #aaa;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="header">
        @if (!empty($company->image_url))
            <img class="logo" src="{{ public_path($company->image_url) }}" alt="Logo">
        @endif
        <div>
            <div class="company-name">{{ $company->name ?? 'Pesantren' }}</div>
            <div class="title">Daftar Hari Libur</div>
            <div class="sub">Periode: {{ $periodLabel }}</div>
        </div>
        <div class="printed">Dicetak: {{ $generatedAt }}</div>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-label">Libur Pesantren</div>
            <div class="stat-value" style="color:#1a6b3c;">{{ $totalCompany }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Libur Nasional</div>
            <div class="stat-value" style="color:#0066cc;">{{ $totalNational }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Total Hari Libur</div>
            <div class="stat-value" style="color:#555;">{{ $totalDays }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:24px;">#</th>
                <th>Nama Hari Libur</th>
                <th style="width:75px;">Mulai</th>
                <th style="width:75px;">Selesai</th>
                <th style="width:50px; text-align:center;">Durasi</th>
                <th style="width:70px; text-align:center;">Tipe</th>
                <th>Keterangan</th>
                <th style="width:60px; text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($holidays as $i => $h)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td style="font-weight:600;">{{ $h['name'] }}</td>
                    <td>{{ \Carbon\Carbon::parse($h['start_date'])->format('d/m/Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($h['end_date'])->format('d/m/Y') }}</td>
                    <td style="text-align:center; font-weight:700;">{{ $h['total_days'] }} hari</td>
                    <td style="text-align:center;">
                        <span class="badge type-{{ $h['type'] }}">
                            {{ $h['type'] === 'company' ? 'Pesantren' : 'Nasional' }}
                        </span>
                    </td>
                    <td>{{ $h['note'] ?? '-' }}</td>
                    <td style="text-align:center;">
                        @if ($h['is_active_today'])
                            <span class="active-today">● Aktif</span>
                        @else
                            <span style="color:#aaa;">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:#aaa; font-style:italic; padding:16px;">Tidak ada
                        data hari libur.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $company->name ?? 'Pesantren' }} &mdash; Daftar Hari Libur &mdash; {{ $generatedAt }}
    </div>
</body>

</html>
