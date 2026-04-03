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
            background: #fff;
            padding: 20px 24px;
        }

        .header {
            margin-bottom: 14px;
            border-bottom: 2px solid #1a6b3c;
            padding-bottom: 10px;
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo {
            height: 48px;
            width: auto;
        }

        .company-name {
            font-size: 14px;
            font-weight: 700;
            color: #1a6b3c;
        }

        .report-title {
            font-size: 17px;
            font-weight: 800;
            color: #111;
            margin-top: 2px;
        }

        .report-sub {
            font-size: 10px;
            color: #666;
            margin-top: 1px;
        }

        .printed-at {
            margin-left: auto;
            font-size: 9px;
            color: #aaa;
            text-align: right;
            white-space: nowrap;
        }

        .stats-row {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }

        .stat-box {
            flex: 1;
            background: #f0faf4;
            border: 1px solid #c3e6cf;
            border-radius: 5px;
            padding: 7px 10px;
        }

        .stat-label {
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 800;
            color: #1a6b3c;
            margin-top: 1px;
        }

        .santri-block {
            margin-bottom: 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .santri-header {
            background: #1a6b3c;
            color: #fff;
            padding: 6px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .santri-name {
            font-size: 11px;
            font-weight: 700;
        }

        .santri-meta {
            font-size: 9px;
            opacity: 0.8;
        }

        .santri-stats {
            display: flex;
            gap: 0;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
        }

        .santri-stat {
            flex: 1;
            padding: 5px 8px;
            border-right: 1px solid #eee;
            text-align: center;
        }

        .santri-stat:last-child {
            border-right: none;
        }

        .santri-stat-label {
            font-size: 8px;
            color: #888;
        }

        .santri-stat-value {
            font-size: 13px;
            font-weight: 700;
            color: #333;
        }

        .rate-good {
            color: #1a6b3c;
        }

        .rate-mid {
            color: #e6a817;
        }

        .rate-bad {
            color: #c0392b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        th {
            background: #e8f5ee;
            color: #1a6b3c;
            font-weight: 700;
            padding: 5px 6px;
            text-align: left;
            border-bottom: 1px solid #c3e6cf;
        }

        td {
            padding: 4px 6px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:nth-child(even) td {
            background: #fafafa;
        }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: 600;
        }

        .badge-green {
            background: #d4edda;
            color: #155724;
        }

        .badge-red {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-yellow {
            background: #fff3cd;
            color: #856404;
        }

        .badge-grey {
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

    {{-- HEADER --}}
    <div class="header">
        <div class="header-top">
            @if (!empty($company->image_url))
                <img class="logo" src="{{ public_path($company->image_url) }}" alt="Logo">
            @endif
            <div>
                <div class="company-name">{{ $company->name ?? 'Pesantren' }}</div>
                <div class="report-title">Rekap Laporan Harian Santri</div>
                <div class="report-sub">Periode: {{ $periodLabel }}</div>
            </div>
            <div class="printed-at">Dicetak: {{ $generatedAt }}</div>
        </div>
    </div>

    {{-- RINGKASAN --}}
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-label">Total Santri</div>
            <div class="stat-value">{{ $summaryPerSantri->count() }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Total Laporan</div>
            <div class="stat-value">{{ $totalReports }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Rata-rata Pencapaian</div>
            @php
                $avgRate = $summaryPerSantri->avg('achievement_rate') ?? 0;
                $rateClass = $avgRate >= 80 ? 'rate-good' : ($avgRate >= 50 ? 'rate-mid' : 'rate-bad');
            @endphp
            <div class="stat-value {{ $rateClass }}">{{ number_format($avgRate, 1) }}%</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Bulan / Tahun</div>
            <div class="stat-value" style="font-size:12px;">{{ $month }} / {{ $year }}</div>
        </div>
    </div>

    {{-- PER SANTRI --}}
    @forelse($summaryPerSantri as $item)
        @php
            $santri = $item['santri'];
            $rate = $item['achievement_rate'];
            $rateClass = $rate >= 80 ? 'rate-good' : ($rate >= 50 ? 'rate-mid' : 'rate-bad');
        @endphp
        <div class="santri-block">
            <div class="santri-header">
                <div>
                    <div class="santri-name">{{ $santri?->name ?? '-' }}</div>
                    <div class="santri-meta">
                        Kamar: {{ $santri?->position ?? '-' }} &nbsp;|&nbsp;
                        Kelas: {{ $santri?->department ?? '-' }}
                    </div>
                </div>
            </div>

            <div class="santri-stats">
                <div class="santri-stat">
                    <div class="santri-stat-label">Total Hari</div>
                    <div class="santri-stat-value">{{ $item['total_days'] }}</div>
                </div>
                <div class="santri-stat">
                    <div class="santri-stat-label">Tercapai</div>
                    <div class="santri-stat-value rate-good">{{ $item['total_achieved'] }}</div>
                </div>
                <div class="santri-stat">
                    <div class="santri-stat-label">Tidak Tercapai</div>
                    <div class="santri-stat-value rate-bad">{{ $item['total_not_achieved'] }}</div>
                </div>
                <div class="santri-stat">
                    <div class="santri-stat-label">Pending</div>
                    <div class="santri-stat-value rate-mid">{{ $item['total_pending'] }}</div>
                </div>
                <div class="santri-stat">
                    <div class="santri-stat-label">Achievement</div>
                    <div class="santri-stat-value {{ $rateClass }}">{{ number_format($rate, 1) }}%</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:70px;">Tanggal</th>
                        <th>Target Pagi</th>
                        <th>Pencapaian Sore</th>
                        <th style="width:70px; text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item['reports'] as $r)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
                            <td>{{ $r->target }}</td>
                            <td>{{ $r->achievement ?? '<span style="color:#aaa;font-style:italic;">Belum diisi</span>' }}
                            </td>
                            <td style="text-align:center;">
                                @if (is_null($r->achievement))
                                    <span class="badge badge-grey">Pending</span>
                                @elseif($r->is_achieved)
                                    <span class="badge badge-green">Tercapai</span>
                                @else
                                    <span class="badge badge-red">Tidak</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p style="color:#aaa; font-style:italic; text-align:center; margin:24px 0;">
            Tidak ada data laporan harian.
        </p>
    @endforelse

    <div class="footer">
        Dokumen ini digenerate secara otomatis &mdash; {{ $company->name ?? 'Pesantren' }} &mdash; {{ $generatedAt }}
    </div>
</body>

</html>
