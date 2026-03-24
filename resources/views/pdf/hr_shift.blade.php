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
            padding: 7px 8px;
            border-bottom: 1px solid #eee;
            font-size: 9px;
            vertical-align: middle;
        }

        tr:nth-child(even) td {
            background: #f8f9fa;
        }

        .default-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
            background: #dbeafe;
            color: #1e40af;
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
        <h1>Daftar Shift Kerja</h1>
        <p>{{ $company->name ?? 'Perusahaan' }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $total }} shift</div>
    </div>

    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $total }}</div>
            <div class="lbl">Total Shift</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $shifts->where('is_default', true)->count() }}</div>
            <div class="lbl">Shift Default</div>
        </div>
    </div>

    <div class="section-title">Detail Shift</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th>Nama Shift</th>
                <th style="width:70px;text-align:center">Jam Mulai</th>
                <th style="width:70px;text-align:center">Jam Selesai</th>
                <th style="width:70px;text-align:center">Durasi</th>
                <th style="width:90px;text-align:center">Toleransi (mnt)</th>
                <th style="width:60px;text-align:center">Default</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($shifts as $i => $s)
                @php
                    // Hitung durasi
                    try {
                        $start =
                            \Carbon\Carbon::createFromFormat('H:i:s', $s->start_time) ?:
                            \Carbon\Carbon::createFromFormat('H:i', $s->start_time);
                        $end =
                            \Carbon\Carbon::createFromFormat('H:i:s', $s->end_time) ?:
                            \Carbon\Carbon::createFromFormat('H:i', $s->end_time);
                        $diffMins = $start->diffInMinutes($end);
                        if ($diffMins < 0) {
                            $diffMins += 24 * 60;
                        }
                        $durasi = floor($diffMins / 60) . 'j ' . $diffMins % 60 . 'm';
                    } catch (\Throwable $e) {
                        $durasi = '-';
                    }
                    $startFmt = substr($s->start_time ?? '--:--', 0, 5);
                    $endFmt = substr($s->end_time ?? '--:--', 0, 5);
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $s->name ?? '-' }}</strong></td>
                    <td style="text-align:center">{{ $startFmt }}</td>
                    <td style="text-align:center">{{ $endFmt }}</td>
                    <td style="text-align:center">{{ $durasi }}</td>
                    <td style="text-align:center">{{ $s->grace_period_minutes ?? 15 }} menit</td>
                    <td style="text-align:center">
                        @if ($s->is_default)
                            <span class="default-badge">Default</span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Daftar Shift &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
