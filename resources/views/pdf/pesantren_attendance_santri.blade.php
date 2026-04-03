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

        .santri-card {
            background: #f0faf4;
            border: 1px solid #c3e6cf;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
            display: flex;
            gap: 16px;
        }

        .sc-label {
            font-size: 8px;
            color: #888;
            text-transform: uppercase;
        }

        .sc-value {
            font-size: 13px;
            font-weight: 700;
            color: #1a6b3c;
            margin-top: 1px;
        }

        .stats-row {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }

        .stat-box {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 6px 8px;
            text-align: center;
        }

        .stat-label {
            font-size: 8px;
            color: #888;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 14px;
            font-weight: 800;
            margin-top: 1px;
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

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: 600;
        }

        .s-on_time {
            background: #d4edda;
            color: #155724;
        }

        .s-late {
            background: #fff3cd;
            color: #856404;
        }

        .s-absent {
            background: #f8d7da;
            color: #721c24;
        }

        .s-permission {
            background: #cce5ff;
            color: #004085;
        }

        .s-overtime {
            background: #e2d9f3;
            color: #4b0082;
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
            <div class="title">Rekap Absensi Santri</div>
            <div class="sub">Periode: {{ $periodLabel }}</div>
        </div>
        <div class="printed">Dicetak: {{ $generatedAt }}</div>
    </div>

    {{-- INFO SANTRI --}}
    <div class="santri-card">
        <div>
            <div class="sc-label">Nama Santri</div>
            <div class="sc-value" style="font-size:15px;">{{ $santri->name }}</div>
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
            <div class="sc-value" style="font-size:11px;">{{ $santri->email ?? '-' }}</div>
        </div>
    </div>

    {{-- STATISTIK --}}
    @if (!empty($stats))
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-label">Total Hari</div>
                <div class="stat-value" style="color:#555;">{{ $stats['total'] ?? 0 }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Tepat Waktu</div>
                <div class="stat-value" style="color:#1a6b3c;">{{ $stats['on_time'] ?? 0 }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Terlambat</div>
                <div class="stat-value" style="color:#e6a817;">{{ $stats['late'] ?? 0 }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Alpha</div>
                <div class="stat-value" style="color:#c0392b;">{{ $stats['absent'] ?? 0 }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Izin</div>
                <div class="stat-value" style="color:#0066cc;">{{ $stats['permission'] ?? 0 }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Terlambat</div>
                <div class="stat-value" style="color:#e6a817; font-size:11px;">{{ $stats['total_late_minutes'] ?? 0 }}
                    mnt</div>
            </div>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width:24px;">#</th>
                <th style="width:80px;">Tanggal</th>
                <th style="width:60px; text-align:center;">Check-in</th>
                <th style="width:60px; text-align:center;">Check-out</th>
                <th style="width:70px; text-align:center;">Jadwal Masuk</th>
                <th style="width:70px; text-align:center;">Jadwal Pulang</th>
                <th style="width:65px; text-align:center;">Status</th>
                <th style="width:60px; text-align:center;">Terlambat</th>
                <th style="width:65px; text-align:center;">Pulang Awal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $i => $a)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($a->date)->format('d/m/Y') }}</td>
                    <td style="text-align:center; font-weight:600;">{{ $a->time_in ? substr($a->time_in, 0, 5) : '-' }}
                    </td>
                    <td style="text-align:center; font-weight:600;">
                        {{ $a->time_out ? substr($a->time_out, 0, 5) : '-' }}</td>
                    <td style="text-align:center; color:#888;">
                        {{ $a->scheduled_in ? substr($a->scheduled_in, 0, 5) : '-' }}</td>
                    <td style="text-align:center; color:#888;">
                        {{ $a->scheduled_out ? substr($a->scheduled_out, 0, 5) : '-' }}</td>
                    <td style="text-align:center;">
                        <span class="badge s-{{ $a->status }}">
                            {{ match ($a->status) {
                                'on_time' => 'Tepat Waktu',
                                'late' => 'Terlambat',
                                'absent' => 'Alpha',
                                'permission' => 'Izin',
                                'overtime' => 'Lembur',
                                default => ucfirst($a->status),
                            } }}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        {{ ($a->late_minutes ?? 0) > 0 ? $a->late_minutes . ' mnt' : '-' }}
                    </td>
                    <td style="text-align:center;">
                        {{ ($a->early_leave_minutes ?? 0) > 0 ? $a->early_leave_minutes . ' mnt' : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center; color:#aaa; font-style:italic; padding:16px;">Tidak ada
                        data absensi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $company->name ?? 'Pesantren' }} &mdash; Rekap Absensi &mdash; {{ $santri->name }} &mdash;
        {{ $generatedAt }}
    </div>
</body>

</html>
