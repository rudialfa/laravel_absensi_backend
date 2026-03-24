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
            gap: 8px;
            margin: 0 20px 14px;
            flex-wrap: wrap;
        }

        .summary-box {
            flex: 1;
            min-width: 60px;
            padding: 8px 10px;
            border-radius: 8px;
            text-align: center;
        }

        .summary-box .num {
            font-size: 16px;
            font-weight: 900;
        }

        .summary-box .lbl {
            font-size: 8.5px;
            font-weight: 600;
            margin-top: 2px;
        }

        .box-blue {
            background: #dbeafe;
            color: #1e40af;
        }

        .box-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .box-yellow {
            background: #fef9c3;
            color: #713f12;
        }

        .box-purple {
            background: #ede9fe;
            color: #5b21b6;
        }

        .box-orange {
            background: #fef3c7;
            color: #92400e;
        }

        .box-grey {
            background: #f3f4f6;
            color: #374151;
        }

        .box-dark {
            background: #1f2937;
            color: white;
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
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
        }

        .badge-warning {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-praise {
            background: #fef9c3;
            color: #713f12;
        }

        .badge-performance {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-absence {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-general {
            background: #f3f4f6;
            color: #374151;
        }

        .unread-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #ef4444;
            vertical-align: middle;
            margin-right: 3px;
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
        <h1>Rekap Catatan Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }} &mdash; {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $stats['total'] }} catatan</div>
    </div>

    {{-- Summary --}}
    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['total'] }}</div>
            <div class="lbl">Total</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $stats['warning'] }}</div>
            <div class="lbl">Peringatan</div>
        </div>
        <div class="summary-box box-yellow">
            <div class="num">{{ $stats['praise'] }}</div>
            <div class="lbl">Pujian</div>
        </div>
        <div class="summary-box box-purple">
            <div class="num">{{ $stats['performance'] }}</div>
            <div class="lbl">Performa</div>
        </div>
        <div class="summary-box box-orange">
            <div class="num">{{ $stats['absence'] }}</div>
            <div class="lbl">Absensi</div>
        </div>
        <div class="summary-box box-grey">
            <div class="num">{{ $stats['general'] }}</div>
            <div class="lbl">Umum</div>
        </div>
        <div class="summary-box box-dark">
            <div class="num">{{ $stats['unread'] }}</div>
            <div class="lbl">Belum Dibaca</div>
        </div>
    </div>

    <div class="section-title">Detail Catatan</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th>Karyawan</th>
                <th>Judul</th>
                <th style="width:65px;text-align:center">Tipe</th>
                <th style="width:50px;text-align:center">Dibaca</th>
                <th>Dibuat Oleh</th>
                <th style="width:65px">Tanggal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($notes as $i => $n)
                @php
                    $badgeClass = match ($n->type) {
                        'warning' => 'badge-warning',
                        'praise' => 'badge-praise',
                        'performance' => 'badge-performance',
                        'absence' => 'badge-absence',
                        default => 'badge-general',
                    };
                    $typeLabel = match ($n->type) {
                        'warning' => 'Peringatan',
                        'praise' => 'Pujian',
                        'performance' => 'Performa',
                        'absence' => 'Absensi',
                        default => 'Umum',
                    };
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $n->user?->name ?? '-' }}</strong><br>
                        <span style="color:#6b7280">{{ $n->user?->department ?? '-' }}</span>
                    </td>
                    <td>
                        {{ $n->title ?? '-' }}
                        @if ($n->note)
                            <br><span style="color:#6b7280; font-size:8px;">{{ \Str::limit($n->note, 60) }}</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        <span class="badge {{ $badgeClass }}">{{ $typeLabel }}</span>
                    </td>
                    <td style="text-align:center">
                        @if (!$n->is_read)
                            <span class="unread-dot"></span>Belum
                        @else
                            Sudah
                        @endif
                    </td>
                    <td>{{ $n->creator?->name ?? '-' }}</td>
                    <td>{{ $n->created_at ? \Carbon\Carbon::parse($n->created_at)->format('d/m/Y') : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Rekap Catatan {{ $periodLabel }} &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
