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
            font-size: 11px;
            color: #1a1a2e;
        }

        .header {
            background: #1a1a2e;
            color: white;
            padding: 20px 24px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .header p {
            font-size: 11px;
            opacity: 0.8;
        }

        .header .meta {
            margin-top: 10px;
            font-size: 10px;
            opacity: 0.7;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #1a1a2e;
            border-left: 4px solid #1a1a2e;
            padding-left: 10px;
            margin: 16px 24px 10px;
        }

        /* Summary table (per karyawan) */
        .summary-table {
            width: calc(100% - 48px);
            margin: 0 24px 20px;
            border-collapse: collapse;
        }

        .summary-table th {
            background: #1a1a2e;
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
        }

        .summary-table td {
            padding: 7px 10px;
            border-bottom: 1px solid #eee;
            font-size: 10px;
        }

        .summary-table tr:nth-child(even) td {
            background: #f8f9fa;
        }

        .rate-bar-bg {
            background: #e9ecef;
            border-radius: 4px;
            height: 8px;
            width: 80px;
            display: inline-block;
            vertical-align: middle;
        }

        .rate-bar {
            background: #198754;
            border-radius: 4px;
            height: 8px;
            display: block;
        }

        /* Per-user report section */
        .user-section {
            margin: 0 24px 20px;
            page-break-inside: avoid;
        }

        .user-header {
            background: #f1f3f5;
            padding: 10px 14px;
            border-radius: 8px 8px 0 0;
            border-left: 4px solid #1a1a2e;
            margin-bottom: 0;
        }

        .user-header strong {
            font-size: 12px;
        }

        .user-header span {
            font-size: 10px;
            color: #666;
            margin-left: 8px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th {
            background: #e9ecef;
            padding: 7px 10px;
            text-align: left;
            font-size: 9.5px;
            color: #444;
        }

        .report-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 9.5px;
            vertical-align: top;
        }

        .report-table tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 700;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .footer {
            margin: 20px 24px 0;
            padding-top: 12px;
            border-top: 1px solid #dee2e6;
            font-size: 9px;
            color: #999;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>

<body>

    {{-- Header --}}
    <div class="header">
        <h1>Laporan Harian Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }} &mdash; Periode {{ $periodLabel }}</p>
        <div class="meta">Dicetak pada: {{ $generatedAt }} &nbsp;|&nbsp; Total laporan: {{ $totalReports }}</div>
    </div>

    {{-- Summary Table --}}
    <div class="section-title">Ringkasan Pencapaian</div>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Karyawan</th>
                <th>Dept</th>
                <th>Total Hari</th>
                <th>Tercapai</th>
                <th>Tdk Tercapai</th>
                <th>Pending</th>
                <th>Rate</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summaryPerUser as $s)
                <tr>
                    <td><strong>{{ $s['user']?->name ?? '-' }}</strong></td>
                    <td>{{ $s['user']?->department ?? '-' }}</td>
                    <td>{{ $s['total_days'] }}</td>
                    <td style="color:#065f46;font-weight:600">{{ $s['total_achieved'] }}</td>
                    <td style="color:#991b1b;font-weight:600">{{ $s['total_not_achieved'] }}</td>
                    <td style="color:#92400e">{{ $s['total_pending'] }}</td>
                    <td>
                        <div class="rate-bar-bg">
                            <div class="rate-bar" style="width:{{ min($s['achievement_rate'], 100) }}%"></div>
                        </div>
                        <span style="margin-left:6px">{{ $s['achievement_rate'] }}%</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Detail per karyawan --}}
    <div class="section-title">Detail Laporan per Karyawan</div>

    @foreach ($summaryPerUser as $s)
        <div class="user-section">
            <div class="user-header">
                <strong>{{ $s['user']?->name ?? '-' }}</strong>
                <span>{{ $s['user']?->position ?? '' }}</span>
                <span>&nbsp;&bull;&nbsp;{{ $s['user']?->department ?? '' }}</span>
                <span style="float:right;color:#198754;font-weight:700">{{ $s['achievement_rate'] }}% tercapai</span>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width:80px">Tanggal</th>
                        <th>Target Pagi</th>
                        <th>Pencapaian Sore</th>
                        <th style="width:80px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($s['reports'] as $r)
                        <tr>
                            <td>{{ $r->date }}</td>
                            <td>{{ $r->target ?? '-' }}</td>
                            <td>
                                @if ($r->achievement)
                                    {{ $r->achievement }}
                                    @if ($r->reason_not_achieved)
                                        <br><span style="color:#991b1b;font-size:9px">⚠
                                            {{ $r->reason_not_achieved }}</span>
                                    @endif
                                @else
                                    <span style="color:#92400e">Belum diisi</span>
                                @endif
                            </td>
                            <td>
                                @if (!$r->achievement)
                                    <span class="badge badge-warning">Pending</span>
                                @elseif($r->is_achieved)
                                    <span class="badge badge-success">Tercapai</span>
                                @else
                                    <span class="badge badge-danger">Tdk Tercapai</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="footer">
        Dokumen ini digenerate otomatis oleh sistem &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
