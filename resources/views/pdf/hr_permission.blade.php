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
            background: #fef9c3;
            color: #713f12;
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
        <h1>Rekap Izin / Cuti Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }} &mdash; {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $stats['total'] }} pengajuan</div>
    </div>

    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['total'] }}</div>
            <div class="lbl">Total</div>
        </div>
        <div class="summary-box box-yellow">
            <div class="num">{{ $stats['pending'] }}</div>
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
    </div>

    <div class="section-title">Detail Pengajuan Izin</div>

    <table>
        <thead>
            <tr>
                <th style="width:22px">No</th>
                <th>Nama Karyawan</th>
                <th>Email</th>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Alasan</th>
                <th style="width:65px;text-align:center">Status</th>
                <th style="width:65px">Diajukan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($permissions as $i => $p)
                @php
                    if ($p->is_approved === null) {
                        $badgeClass = 'badge-pending';
                        $statusLabel = 'Menunggu';
                    } elseif ($p->is_approved) {
                        $badgeClass = 'badge-approved';
                        $statusLabel = 'Disetujui';
                    } else {
                        $badgeClass = 'badge-rejected';
                        $statusLabel = 'Ditolak';
                    }
                    $tanggal = $p->date_permission ?? ($p->date ?? '-');
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $p->user?->name ?? '-' }}</strong></td>
                    <td>{{ $p->user?->email ?? '-' }}</td>
                    <td>{{ $tanggal }}</td>
                    <td>{{ $p->type ?? '-' }}</td>
                    <td>{{ \Str::limit($p->reason ?? '-', 50) }}</td>
                    <td style="text-align:center">
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td>{{ $p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('d/m/Y') : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Rekap Izin/Cuti {{ $periodLabel }} &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
