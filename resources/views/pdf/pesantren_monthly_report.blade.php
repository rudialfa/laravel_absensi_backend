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
            color: #222;
            padding: 20px 24px;
        }

        .header {
            border-bottom: 2px solid #1a6b3c;
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo {
            height: 48px;
        }

        .company-name {
            font-size: 14px;
            font-weight: 700;
            color: #1a6b3c;
        }

        .title {
            font-size: 17px;
            font-weight: 800;
            color: #111;
            margin-top: 2px;
        }

        .sub {
            font-size: 10px;
            color: #666;
        }

        .printed {
            margin-left: auto;
            font-size: 9px;
            color: #aaa;
            text-align: right;
        }

        .stats-row {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }

        .stat-box {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 7px 10px;
            text-align: center;
        }

        .stat-label {
            font-size: 8px;
            color: #888;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 15px;
            font-weight: 800;
            margin-top: 2px;
        }

        .green {
            color: #1a6b3c;
        }

        .yellow {
            color: #e6a817;
        }

        .red {
            color: #c0392b;
        }

        .grey {
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }

        th {
            background: #e8f5ee;
            color: #1a6b3c;
            font-weight: 700;
            padding: 5px 7px;
            text-align: left;
            border-bottom: 1px solid #c3e6cf;
        }

        td {
            padding: 5px 7px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        tr:nth-child(even) td {
            background: #fafafa;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: 600;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-submitted {
            background: #cce5ff;
            color: #004085;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-draft {
            background: #e9ecef;
            color: #495057;
        }

        .footer {
            margin-top: 16px;
            border-top: 1px solid #eee;
            padding-top: 6px;
            font-size: 8px;
            color: #aaa;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="header">
        @if (!empty($company->image_url))
            <img class="logo" src="{{ public_path($company->image_url) }}" alt="Logo">
        @endif
        <div>
            <div class="company-name">{{ $company->name ?? 'Pesantren' }}</div>
            <div class="title">Rekap Laporan Bulanan Santri</div>
            <div class="sub">Periode: {{ $periodLabel }}</div>
        </div>
        <div class="printed">Dicetak: {{ $generatedAt }}</div>
    </div>

    {{-- STATS --}}
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-label">Total</div>
            <div class="stat-value grey">{{ $stats['total'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Approved</div>
            <div class="stat-value green">{{ $stats['approved'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Submitted</div>
            <div class="stat-value" style="color:#0066cc;">{{ $stats['submitted'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Ditolak</div>
            <div class="stat-value red">{{ $stats['rejected'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Draft</div>
            <div class="stat-value grey">{{ $stats['draft'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Rata-rata Skor</div>
            <div class="stat-value green">{{ $stats['avg_score'] }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:24px;">#</th>
                <th>Nama Santri</th>
                <th>Kamar / Kelas</th>
                <th style="width:60px;">Bulan/Tahun</th>
                <th>Target</th>
                <th>Pencapaian</th>
                <th>Masalah & Solusi</th>
                <th style="width:55px; text-align:center;">Skor</th>
                <th style="width:60px; text-align:center;">Status</th>
                <th>Disetujui Oleh</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td style="font-weight:600;">{{ $r->user?->name ?? '-' }}</td>
                    <td>{{ $r->user?->position ?? '-' }} / {{ $r->user?->department ?? '-' }}</td>
                    <td>{{ $r->month }}/{{ $r->year }}</td>
                    <td style="max-width:120px; word-wrap:break-word;">{{ Str::limit($r->target, 60) }}</td>
                    <td style="max-width:120px; word-wrap:break-word;">{{ Str::limit($r->achievement, 60) }}</td>
                    <td style="max-width:100px; word-wrap:break-word; font-size:8.5px; color:#555;">
                        @if ($r->problem)
                            <b>M:</b> {{ Str::limit($r->problem, 40) }}<br>
                        @endif
                        @if ($r->solution)
                            <b>S:</b> {{ Str::limit($r->solution, 40) }}
                        @endif
                    </td>
                    <td
                        style="text-align:center; font-weight:700;
                    color:{{ $r->score >= 80 ? '#1a6b3c' : ($r->score >= 60 ? '#e6a817' : '#c0392b') }}">
                        {{ $r->score > 0 ? number_format($r->score, 1) : '-' }}
                    </td>
                    <td style="text-align:center;">
                        @php $status = $r->status; @endphp
                        <span class="badge badge-{{ $status }}">{{ ucfirst($status) }}</span>
                    </td>
                    <td>{{ $r->approver?->name ?? '-' }}
                        @if ($r->approved_at)
                            <br><span style="font-size:8px; color:#aaa;">
                                {{ \Carbon\Carbon::parse($r->approved_at)->format('d/m/Y') }}
                            </span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center; color:#aaa; font-style:italic; padding:16px;">Tidak ada
                        data laporan bulanan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $company->name ?? 'Pesantren' }} &mdash; Rekap Laporan Bulanan &mdash; {{ $generatedAt }}
    </div>
</body>

</html>
