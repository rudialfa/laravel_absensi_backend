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
        }

        .summary-box {
            flex: 1;
            padding: 10px 12px;
            border-radius: 8px;
            text-align: center;
        }

        .summary-box .num {
            font-size: 18px;
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

        .box-orange {
            background: #fef3c7;
            color: #92400e;
        }

        .box-grey {
            background: #f3f4f6;
            color: #374151;
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

        .badge-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-submitted {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-draft {
            background: #f3f4f6;
            color: #374151;
        }

        .score-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 900;
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
        <h1>Rekap Laporan Bulanan Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }} &mdash; Periode {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total laporan: {{ $reports->count() }}</div>
    </div>

    {{-- Summary boxes --}}
    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['total'] }}</div>
            <div class="lbl">Total</div>
        </div>
        <div class="summary-box box-orange">
            <div class="num">{{ $stats['submitted'] }}</div>
            <div class="lbl">Menunggu</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $stats['approved'] }}</div>
            <div class="lbl">Disetujui</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $stats['rejected'] }}</div>
            <div class="lbl">Ditolak</div>
        </div>
        <div class="summary-box box-grey">
            <div class="num">{{ $stats['draft'] }}</div>
            <div class="lbl">Draft</div>
        </div>
    </div>

    @if ($stats['avg_score'] > 0)
        <div
            style="margin:0 20px 14px; padding:10px 14px; background:#dbeafe; border-radius:8px; border:1px solid #93c5fd;">
            <span style="font-size:10px; color:#1e40af; font-weight:700;">
                Rata-rata skor laporan disetujui: {{ $stats['avg_score'] }}
            </span>
        </div>
    @endif

    <div class="section-title">Detail Laporan Bulanan</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th>Nama Karyawan</th>
                <th>Dept / Posisi</th>
                <th style="width:50px;text-align:center">Bulan</th>
                <th style="width:40px;text-align:center">Tahun</th>
                <th style="width:65px;text-align:center">Status</th>
                <th style="width:45px;text-align:center">Skor</th>
                <th>Disetujui Oleh</th>
                <th style="width:70px">Tanggal Approve</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reports as $i => $r)
                @php
                    $badgeClass = match ($r->status) {
                        'approved' => 'badge-approved',
                        'rejected' => 'badge-rejected',
                        'submitted' => 'badge-submitted',
                        default => 'badge-draft',
                    };
                    $statusLabel = match ($r->status) {
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        'submitted' => 'Menunggu',
                        default => 'Draft',
                    };
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $r->user?->name ?? '-' }}</strong></td>
                    <td>{{ $r->user?->department ?? '-' }} / {{ $r->user?->position ?? '-' }}</td>
                    <td style="text-align:center">{{ $r->month }}</td>
                    <td style="text-align:center">{{ $r->year }}</td>
                    <td style="text-align:center">
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td style="text-align:center">
                        @if ($r->score !== null)
                            <span class="score-badge">{{ number_format($r->score, 1) }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $r->approver?->name ?? '-' }}</td>
                    <td>{{ $r->approved_at ? \Carbon\Carbon::parse($r->approved_at)->format('d/m/Y') : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Rekap Laporan Bulanan {{ $periodLabel }} &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
