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

        .box-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .box-yellow {
            background: #fef9c3;
            color: #713f12;
        }

        .box-gray {
            background: #f3f4f6;
            color: #374151;
        }

        .avg-score {
            margin: 0 20px 14px;
            padding: 10px 16px;
            background: #ede9fe;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            color: #5b21b6;
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

        .badge-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-submitted {
            background: #fef9c3;
            color: #713f12;
        }

        .badge-draft {
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
        <h1>Rekap Laporan Bulanan</h1>
        <p>{{ $user->name ?? '-' }} &mdash; {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $stats['total'] }} laporan</div>
    </div>

    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['total'] }}</div>
            <div class="lbl">Total</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $stats['approved'] }}</div>
            <div class="lbl">Disetujui</div>
        </div>
        <div class="summary-box box-yellow">
            <div class="num">{{ $stats['submitted'] }}</div>
            <div class="lbl">Menunggu</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $stats['rejected'] }}</div>
            <div class="lbl">Ditolak</div>
        </div>
        <div class="summary-box box-gray">
            <div class="num">{{ $stats['draft'] }}</div>
            <div class="lbl">Draft</div>
        </div>
    </div>

    @if ($stats['avg_score'] > 0)
        <div class="avg-score">
            Rata-rata Skor (Approved): {{ $stats['avg_score'] }}
        </div>
    @endif

    <div class="section-title">Detail Laporan Bulanan</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th style="width:42px">Bln/Thn</th>
                <th style="width:20%">Target</th>
                <th style="width:20%">Pencapaian</th>
                <th style="width:18%">Kendala</th>
                <th style="width:18%">Solusi</th>
                <th style="width:40px;text-align:center">Skor</th>
                <th style="width:55px;text-align:center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reports as $i => $r)
                @php
                    switch ($r->status) {
                        case 'approved':
                            $bc = 'badge-approved';
                            $bl = 'Disetujui';
                            break;
                        case 'rejected':
                            $bc = 'badge-rejected';
                            $bl = 'Ditolak';
                            break;
                        case 'submitted':
                            $bc = 'badge-submitted';
                            $bl = 'Menunggu';
                            break;
                        default:
                            $bc = 'badge-draft';
                            $bl = 'Draft';
                            break;
                    }
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $bulanLabel[$r->month] ?? $r->month }}<br>{{ $r->year }}</td>
                    <td>{{ $r->target ?? '-' }}</td>
                    <td>{{ $r->achievement ?? '-' }}</td>
                    <td>{{ $r->problem ?? '-' }}</td>
                    <td>{{ $r->solution ?? '-' }}</td>
                    <td style="text-align:center">
                        {{ $r->score !== null ? number_format($r->score, 1) : '-' }}
                    </td>
                    <td style="text-align:center">
                        <span class="badge {{ $bc }}">{{ $bl }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Laporan Bulanan {{ $user->name ?? '' }} &mdash; {{ $periodLabel }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
