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

        .summary-row {
            display: flex;
            gap: 10px;
            margin: 0 20px 14px;
        }

        .summary-box {
            flex: 1;
            padding: 10px 14px;
            border-radius: 8px;
            text-align: center;
        }

        .summary-box .num {
            font-size: 20px;
            font-weight: 900;
        }

        .summary-box .lbl {
            font-size: 9px;
            font-weight: 600;
            margin-top: 2px;
        }

        .box-blue {
            background: #dbeafe;
            color: #1e40af;
        }

        .box-purple {
            background: #ede9fe;
            color: #5b21b6;
        }

        .box-orange {
            background: #fef3c7;
            color: #92400e;
        }

        .box-green {
            background: #d1fae5;
            color: #065f46;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #1a1a2e;
            border-left: 4px solid #1a1a2e;
            padding-left: 8px;
            margin: 0 20px 8px;
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
            vertical-align: top;
        }

        tr:nth-child(even) td {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
        }

        .badge-company {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-national {
            background: #fef3c7;
            color: #92400e;
        }

        .active-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            margin-right: 4px;
            vertical-align: middle;
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
        <h1>Rekap Hari Libur Perusahaan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }} &mdash; Periode {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $holidays->count() }} hari libur</div>
    </div>

    {{-- Summary boxes --}}
    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $holidays->count() }}</div>
            <div class="lbl">Total Libur</div>
        </div>
        <div class="summary-box box-purple">
            <div class="num">{{ $totalCompany }}</div>
            <div class="lbl">Company</div>
        </div>
        <div class="summary-box box-orange">
            <div class="num">{{ $totalNational }}</div>
            <div class="lbl">Nasional</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $totalDays }}</div>
            <div class="lbl">Total Hari</div>
        </div>
    </div>

    <div class="section-title">Daftar Hari Libur</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th>Nama Hari Libur</th>
                <th style="width:70px">Mulai</th>
                <th style="width:70px">Akhir</th>
                <th style="width:40px;text-align:center">Hari</th>
                <th style="width:60px;text-align:center">Tipe</th>
                <th style="width:50px;text-align:center">Aktif</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($holidays as $i => $h)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $h['name'] }}</strong></td>
                    <td>{{ $h['start_date'] }}</td>
                    <td>{{ $h['end_date'] }}</td>
                    <td style="text-align:center"><strong>{{ $h['total_days'] }}</strong></td>
                    <td style="text-align:center">
                        <span class="badge {{ $h['type'] === 'national' ? 'badge-national' : 'badge-company' }}">
                            {{ $h['type'] === 'national' ? 'Nasional' : 'Company' }}
                        </span>
                    </td>
                    <td style="text-align:center">
                        @if ($h['is_active_today'])
                            <span class="active-dot"></span>Ya
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $h['note'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Rekap Hari Libur {{ $periodLabel }} &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
