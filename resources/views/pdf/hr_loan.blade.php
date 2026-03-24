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
            font-size: 16px;
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

        .box-grey {
            background: #f3f4f6;
            color: #374151;
        }

        .box-purple {
            background: #ede9fe;
            color: #5b21b6;
        }

        .box-dark {
            background: #1f2937;
            color: white;
        }

        .box-teal {
            background: #ccfbf1;
            color: #065f46;
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
            vertical-align: middle;
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

        .badge-pending {
            background: #fef9c3;
            color: #713f12;
        }

        .badge-active {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-canceled {
            background: #f3f4f6;
            color: #374151;
        }

        .progress-wrap {
            width: 60px;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            display: inline-block;
        }

        .progress-bar {
            height: 100%;
            border-radius: 3px;
            background: #3b82f6;
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
        <h1>Rekap Pinjaman Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }}{{ $statusLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $stats['total'] }} pinjaman</div>
    </div>

    {{-- Summary --}}
    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['total'] }}</div>
            <div class="lbl">Total</div>
        </div>
        <div class="summary-box box-yellow">
            <div class="num">{{ $stats['pending'] }}</div>
            <div class="lbl">Pending</div>
        </div>
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['active'] }}</div>
            <div class="lbl">Aktif</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $stats['paid'] }}</div>
            <div class="lbl">Lunas</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $stats['rejected'] }}</div>
            <div class="lbl">Ditolak</div>
        </div>
        <div class="summary-box box-grey">
            <div class="num">{{ $stats['canceled'] }}</div>
            <div class="lbl">Dibatalkan</div>
        </div>
        <div class="summary-box box-purple">
            <div class="num">Rp {{ number_format($stats['total_balance'], 0, ',', '.') }}</div>
            <div class="lbl">Total Sisa</div>
        </div>
        <div class="summary-box box-teal">
            <div class="num">Rp {{ number_format($stats['total_disbursed'], 0, ',', '.') }}</div>
            <div class="lbl">Total Dicairkan</div>
        </div>
    </div>

    <div class="section-title">Detail Pinjaman</div>

    <table>
        <thead>
            <tr>
                <th style="width:20px">No</th>
                <th>Karyawan</th>
                <th>Dept / Posisi</th>
                <th style="width:75px;text-align:right">Pinjaman</th>
                <th style="width:75px;text-align:right">Sisa</th>
                <th style="width:55px;text-align:center">Cicilan</th>
                <th style="width:75px;text-align:right">Cicilan/Bln</th>
                <th style="width:55px">Kategori</th>
                <th style="width:60px;text-align:center">Status</th>
                <th style="width:70px;text-align:center">Progress</th>
                <th style="width:55px">Dibuat</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($loans as $i => $l)
                @php
                    $amount = (float) $l->amount;
                    $balance = (float) $l->balance;
                    $paid = $amount - $balance;
                    $progress = $amount > 0 ? round(($paid / $amount) * 100, 1) : 0;
                    $barWidth = min(100, $progress) . '%';
                    $badgeClass = 'badge-' . ($l->status ?? 'canceled');
                    $statusLabel = match ($l->status) {
                        'pending' => 'Pending',
                        'active' => 'Aktif',
                        'paid' => 'Lunas',
                        'rejected' => 'Ditolak',
                        'canceled' => 'Batal',
                        default => $l->status ?? '-',
                    };
                    $catLabel = match ($l->purpose_category) {
                        'education' => 'Pendidikan',
                        'health' => 'Kesehatan',
                        'emergency' => 'Darurat',
                        'renovation' => 'Renovasi',
                        'business' => 'Bisnis',
                        default => 'Lainnya',
                    };
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $l->user?->name ?? '-' }}</strong></td>
                    <td>{{ $l->user?->department ?? '-' }} / {{ $l->user?->position ?? '-' }}</td>
                    <td style="text-align:right">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                    <td style="text-align:right">Rp {{ number_format($balance, 0, ',', '.') }}</td>
                    <td style="text-align:center">{{ $l->installments ?? '-' }}x</td>
                    <td style="text-align:right">Rp {{ number_format((float) $l->monthly_installment, 0, ',', '.') }}
                    </td>
                    <td>{{ $catLabel }}</td>
                    <td style="text-align:center">
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td style="text-align:center">
                        <span class="progress-wrap">
                            <span class="progress-bar" style="width:{{ $barWidth }}"></span>
                        </span>
                        <br><span style="font-size:7px">{{ $progress }}%</span>
                    </td>
                    <td>{{ $l->created_at ? \Carbon\Carbon::parse($l->created_at)->format('d/m/Y') : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Rekap Pinjaman &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
