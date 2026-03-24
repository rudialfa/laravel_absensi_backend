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

        .box-orange {
            background: #fef3c7;
            color: #92400e;
        }

        .box-green {
            background: #d1fae5;
            color: #065f46;
        }

        .box-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .box-blue {
            background: #dbeafe;
            color: #1e40af;
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

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
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
        <h1>Rekap Pengajuan Lembur</h1>
        <p>{{ $company->name ?? 'Perusahaan' }} &mdash; Periode {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total pengajuan: {{ $overtimes->count() }}</div>
    </div>

    {{-- Summary boxes --}}
    <div class="summary-row">
        <div class="summary-box box-orange">
            <div class="num">{{ $totalPending }}</div>
            <div class="lbl">Menunggu</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $totalApproved }}</div>
            <div class="lbl">Disetujui</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $totalRejected }}</div>
            <div class="lbl">Ditolak</div>
        </div>
        <div class="summary-box box-blue">
            <div class="num">{{ $totalMinutes }}</div>
            <div class="lbl">Total Menit (Approved)</div>
        </div>
    </div>

    <div class="section-title">Detail Pengajuan Lembur</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th>Karyawan</th>
                <th>Dept</th>
                <th style="width:70px">Tanggal</th>
                <th style="width:50px;text-align:center">Menit</th>
                <th>Alasan</th>
                <th style="width:65px;text-align:center">Status</th>
                <th>Catatan Approver</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($overtimes as $i => $ot)
                @php
                    $badgeClass = match ($ot->status) {
                        'approved' => 'badge-approved',
                        'rejected' => 'badge-rejected',
                        default => 'badge-pending',
                    };
                    $statusLabel = match ($ot->status) {
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => 'Menunggu',
                    };
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $ot->user?->name ?? '-' }}</strong></td>
                    <td>{{ $ot->user?->department ?? '-' }}</td>
                    <td>{{ $ot->date }}</td>
                    <td style="text-align:center"><strong>{{ $ot->minutes ?? 0 }}</strong></td>
                    <td>{{ $ot->reason ?? '-' }}</td>
                    <td style="text-align:center">
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td>{{ $ot->approval_note ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($totalMinutes > 0)
        <div
            style="margin:10px 20px; padding:10px 14px; background:#d1fae5; border-radius:8px; border:1px solid #6ee7b7;">
            <span style="font-size:10px; color:#065f46; font-weight:600;">
                Total lembur disetujui: {{ $totalMinutes }} menit
                ({{ floor($totalMinutes / 60) }} jam {{ $totalMinutes % 60 }} menit)
            </span>
        </div>
    @endif

    <div class="footer">
        Rekap Lembur {{ $periodLabel }} &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
