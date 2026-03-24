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
            color: #1a1a2e;
        }

        .header {
            background: #1a1a2e;
            color: white;
            padding: 16px 20px;
            margin-bottom: 16px;
        }

        .header h1 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .header p {
            font-size: 10px;
            opacity: .8;
        }

        .header .meta {
            margin-top: 8px;
            font-size: 9px;
            opacity: .65;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #1a1a2e;
            border-left: 4px solid #1a1a2e;
            padding-left: 8px;
            margin: 14px 20px 8px;
        }

        table {
            width: calc(100% - 40px);
            margin: 0 20px;
            border-collapse: collapse;
        }

        th {
            background: #1a1a2e;
            color: white;
            padding: 7px 8px;
            text-align: left;
            font-size: 9px;
            font-weight: 600;
        }

        td {
            padding: 6px 8px;
            border-bottom: 1px solid #eee;
            font-size: 9px;
        }

        tr:nth-child(even) td {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 8.5px;
            font-weight: 700;
        }

        .badge-green {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-orange {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-grey {
            background: #f3f4f6;
            color: #374151;
        }

        .footer {
            margin: 16px 20px 0;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
            font-size: 8.5px;
            color: #999;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>Rekap Absensi Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }} &mdash; Periode {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total karyawan: {{ $summaryData->count() }}</div>
    </div>

    <div class="section-title">Ringkasan Kehadiran Semua Karyawan</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th>Nama Karyawan</th>
                <th>Jabatan</th>
                <th>Departemen</th>
                <th style="text-align:center">Hadir</th>
                <th style="text-align:center">Tepat Waktu</th>
                <th style="text-align:center">Terlambat</th>
                <th style="text-align:center">Tidak Hadir</th>
                <th style="text-align:center">Total Terlambat</th>
                <th style="text-align:center">Pulang Awal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summaryData as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $row['employee']->name }}</strong></td>
                    <td>{{ $row['employee']->position ?? '-' }}</td>
                    <td>{{ $row['employee']->department ?? '-' }}</td>
                    <td style="text-align:center">
                        <span class="badge badge-green">{{ $row['hadir'] }}</span>
                    </td>
                    <td style="text-align:center">{{ $row['on_time'] }}</td>
                    <td style="text-align:center">
                        @if ($row['late'] > 0)
                            <span class="badge badge-orange">{{ $row['late'] }}</span>
                        @else
                            <span class="badge badge-grey">0</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        @if ($row['absent'] > 0)
                            <span class="badge badge-red">{{ $row['absent'] }}</span>
                        @else
                            <span class="badge badge-grey">0</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        {{ $row['total_late_min'] > 0 ? $row['total_late_min'] . ' mnt' : '-' }}
                    </td>
                    <td style="text-align:center">
                        {{ $row['total_early_min'] > 0 ? $row['total_early_min'] . ' mnt' : '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Rekap Absensi {{ $periodLabel }} &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
