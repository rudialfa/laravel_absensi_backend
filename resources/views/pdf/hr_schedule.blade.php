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
            font-size: 9px;
            color: #1a1a2e;
        }

        .header {
            background: #1a1a2e;
            color: white;
            padding: 12px 18px;
            margin-bottom: 14px;
        }

        .header h1 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .header p {
            font-size: 9px;
            opacity: .8;
        }

        .header .meta {
            margin-top: 6px;
            font-size: 8px;
            opacity: .65;
        }

        .summary-row {
            display: flex;
            gap: 8px;
            margin: 0 18px 12px;
        }

        .summary-box {
            flex: 1;
            padding: 8px 10px;
            border-radius: 6px;
            text-align: center;
        }

        .summary-box .num {
            font-size: 18px;
            font-weight: 900;
        }

        .summary-box .lbl {
            font-size: 8px;
            font-weight: 600;
            margin-top: 2px;
        }

        .box-blue {
            background: #dbeafe;
            color: #1e40af;
        }

        .box-yellow {
            background: #fef9c3;
            color: #713f12;
        }

        .box-green {
            background: #d1fae5;
            color: #065f46;
        }

        .box-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .section-title {
            font-size: 10px;
            font-weight: 700;
            color: #1a1a2e;
            border-left: 4px solid #1a1a2e;
            padding-left: 8px;
            margin: 0 18px 6px;
        }

        table {
            width: calc(100% - 36px);
            margin: 0 18px;
            border-collapse: collapse;
        }

        th {
            background: #1a1a2e;
            color: white;
            padding: 6px 7px;
            text-align: left;
            font-size: 8px;
            font-weight: 600;
        }

        td {
            padding: 5px 7px;
            border-bottom: 1px solid #eee;
            font-size: 8.5px;
            vertical-align: top;
        }

        tr:nth-child(even) td {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 7.5px;
            font-weight: 700;
        }

        .badge-upcoming {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-done {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-canceled {
            background: #fee2e2;
            color: #991b1b;
        }

        .type-badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 7.5px;
            font-weight: 700;
        }

        .type-meeting {
            background: #ede9fe;
            color: #5b21b6;
        }

        .type-task_duty {
            background: #fef9c3;
            color: #713f12;
        }

        .type-visit {
            background: #ccfbf1;
            color: #065f46;
        }

        .type-training {
            background: #fce7f3;
            color: #9d174d;
        }

        .type-other {
            background: #f3f4f6;
            color: #374151;
        }

        .footer {
            margin: 12px 18px 0;
            padding-top: 8px;
            border-top: 1px solid #dee2e6;
            font-size: 8px;
            color: #999;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>Rekap Jadwal Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $stats['total'] }} jadwal</div>
    </div>

    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['total'] }}</div>
            <div class="lbl">Total</div>
        </div>
        <div class="summary-box box-yellow">
            <div class="num">{{ $stats['upcoming'] }}</div>
            <div class="lbl">Mendatang</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $stats['done'] }}</div>
            <div class="lbl">Selesai</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $stats['canceled'] }}</div>
            <div class="lbl">Dibatalkan</div>
        </div>
    </div>

    <div class="section-title">Detail Jadwal</div>

    <table>
        <thead>
            <tr>
                <th style="width:20px">No</th>
                <th>Judul</th>
                <th>Karyawan</th>
                <th style="width:60px;text-align:center">Tipe</th>
                <th style="width:100px">Mulai</th>
                <th style="width:100px">Selesai</th>
                <th style="width:55px;text-align:center">Status</th>
                <th style="width:35px;text-align:center">Peserta</th>
                <th style="width:40px;text-align:center">Berulang</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($schedules as $i => $s)
                @php
                    $statusClass = 'badge-' . ($s->status ?? 'upcoming');
                    $typeClass = 'type-' . ($s->type ?? 'other');
                    $typeLabel = $typeLabels[$s->type ?? 'other'] ?? ucfirst($s->type ?? '-');
                    $statusLabel = match ($s->status) {
                        'upcoming' => 'Mendatang',
                        'done' => 'Selesai',
                        'canceled' => 'Dibatalkan',
                        default => $s->status ?? '-',
                    };
                    $startFmt = $s->start_datetime
                        ? \Carbon\Carbon::parse($s->start_datetime)->format('d/m/Y H:i')
                        : '-';
                    $endFmt = $s->end_datetime ? \Carbon\Carbon::parse($s->end_datetime)->format('d/m/Y H:i') : '-';
                    $participantCount = $s->participants ? $s->participants->count() : 0;
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $s->title ?? '-' }}</strong>
                        @if ($s->description)
                            <br><span style="color:#6b7280; font-size:8px">{{ \Str::limit($s->description, 50) }}</span>
                        @endif
                    </td>
                    <td>
                        {{ $s->user?->name ?? '-' }}
                        @if ($s->user?->department)
                            <br><span style="color:#6b7280">{{ $s->user->department }}</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        <span class="type-badge {{ $typeClass }}">{{ $typeLabel }}</span>
                    </td>
                    <td>{{ $startFmt }}</td>
                    <td>{{ $endFmt }}</td>
                    <td style="text-align:center">
                        <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td style="text-align:center">{{ $participantCount }}</td>
                    <td style="text-align:center">{{ $s->is_recurring ? 'Ya' : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Rekap Jadwal &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
