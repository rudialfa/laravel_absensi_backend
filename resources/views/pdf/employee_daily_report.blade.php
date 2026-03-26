{{-- <!DOCTYPE html>
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

        .box-green {
            background: #d1fae5;
            color: #065f46;
        }

        .box-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .box-yellow {
            background: #fef9c3;
            color: #713f12;
        }

        .rate-bar-wrap {
            margin: 0 20px 14px;
        }

        .rate-bar-bg {
            height: 10px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .rate-bar-fill {
            height: 10px;
            background: #1a1a2e;
            border-radius: 999px;
        }

        .rate-label {
            font-size: 10px;
            color: #6b7280;
            margin-top: 4px;
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

        .badge-achieved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-not {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-pending {
            background: #fef9c3;
            color: #713f12;
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
        <h1>Rekap Daily Report</h1>
        <p>{{ $user->name ?? '-' }} &mdash; {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $stats['total'] }} hari laporan</div>
    </div>

    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['total'] }}</div>
            <div class="lbl">Total Hari</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $stats['achieved'] }}</div>
            <div class="lbl">Tercapai</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $stats['not_achieved'] }}</div>
            <div class="lbl">Tidak Tercapai</div>
        </div>
        <div class="summary-box box-yellow">
            <div class="num">{{ $stats['pending_evening'] }}</div>
            <div class="lbl">Belum Submit Sore</div>
        </div>
    </div>

    <div class="rate-bar-wrap">
        <div style="font-size:10px; font-weight:700; color:#1a1a2e; margin-bottom:5px;">
            Achievement Rate: {{ $achievementRate }}%
        </div>
        <div class="rate-bar-bg">
            <div class="rate-bar-fill" style="width:{{ $achievementRate }}%;"></div>
        </div>
    </div>

    <div class="section-title">Detail Laporan Harian</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th style="width:65px">Tanggal</th>
                <th>Target Pagi</th>
                <th>Pencapaian Sore</th>
                <th>Alasan Tidak Tercapai</th>
                <th style="width:70px;text-align:center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reports as $i => $r)
                @php
                    if ($r->achievement === null) {
                        $badgeClass = 'badge-pending';
                        $statusLabel = 'Belum Submit';
                    } elseif ($r->is_achieved) {
                        $badgeClass = 'badge-achieved';
                        $statusLabel = 'Tercapai';
                    } else {
                        $badgeClass = 'badge-not';
                        $statusLabel = 'Tidak Tercapai';
                    }
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->date ?? '-' }}</td>
                    <td>{{ \Str::limit($r->target ?? '-', 80) }}</td>
                    <td>{{ \Str::limit($r->achievement ?? '-', 80) }}</td>
                    <td>{{ \Str::limit($r->reason_not_achieved ?? '-', 60) }}</td>
                    <td style="text-align:center">
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Daily Report {{ $user->name ?? '' }} &mdash; {{ $periodLabel }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html> --}}




{{-- kode 2 --}}

{{-- FILE: resources/views/pdf/employee_daily_report.blade.php --}}
<!DOCTYPE html>
<html lang="id">

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

        .box-green {
            background: #d1fae5;
            color: #065f46;
        }

        .box-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .box-yellow {
            background: #fef9c3;
            color: #713f12;
        }

        .rate-bar-wrap {
            margin: 0 20px 14px;
        }

        .rate-bar-bg {
            height: 10px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .rate-bar-fill {
            height: 10px;
            background: #1a1a2e;
            border-radius: 999px;
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
            table-layout: fixed;
        }

        th {
            background: #1a1a2e;
            color: white;
            padding: 7px 8px;
            text-align: left;
            font-size: 9px;
            font-weight: 600;
            word-wrap: break-word;
        }

        td {
            padding: 6px 8px;
            border-bottom: 1px solid #eee;
            font-size: 9px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
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

        .badge-achieved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-not {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-pending {
            background: #fef9c3;
            color: #713f12;
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
        <h1>Rekap Daily Report</h1>
        <p>{{ $user->name ?? '-' }} &mdash; {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $stats['total'] }} hari laporan</div>
    </div>

    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['total'] }}</div>
            <div class="lbl">Total Hari</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $stats['achieved'] }}</div>
            <div class="lbl">Tercapai</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $stats['not_achieved'] }}</div>
            <div class="lbl">Tidak Tercapai</div>
        </div>
        <div class="summary-box box-yellow">
            <div class="num">{{ $stats['pending_evening'] }}</div>
            <div class="lbl">Belum Submit Sore</div>
        </div>
    </div>

    <div class="rate-bar-wrap">
        <div style="font-size:10px; font-weight:700; color:#1a1a2e; margin-bottom:5px;">
            Achievement Rate: {{ $achievementRate }}%
        </div>
        <div class="rate-bar-bg">
            <div class="rate-bar-fill" style="width:{{ $achievementRate }}%;"></div>
        </div>
    </div>

    <div class="section-title">Detail Laporan Harian</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th style="width:55px">Tanggal</th>
                <th style="width:22%">Target Pagi</th>
                <th style="width:22%">Pencapaian Sore</th>
                <th style="width:22%">Alasan Tidak Tercapai</th>
                <th style="width:60px;text-align:center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reports as $i => $r)
                @php
                    if ($r->achievement === null) {
                        $badgeClass = 'badge-pending';
                        $statusLabel = 'Belum Submit';
                    } elseif ($r->is_achieved) {
                        $badgeClass = 'badge-achieved';
                        $statusLabel = 'Tercapai';
                    } else {
                        $badgeClass = 'badge-not';
                        $statusLabel = 'Tidak Tercapai';
                    }
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->date ?? '-' }}</td>
                    <td>{{ $r->target ?? '-' }}</td>
                    <td>{{ $r->achievement ?? '-' }}</td>
                    <td>{{ $r->reason_not_achieved ?? '-' }}</td>
                    <td style="text-align:center">
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Daily Report {{ $user->name ?? '' }} &mdash; {{ $periodLabel }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
