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

        .box-yellow {
            background: #fef9c3;
            color: #713f12;
        }

        .box-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .box-purple {
            background: #ede9fe;
            color: #5b21b6;
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
            vertical-align: middle;
        }

        tr:nth-child(even) td {
            background: #f8f9fa;
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 9px;
            font-weight: 900;
        }

        .rank-1 {
            background: #fef9c3;
            color: #713f12;
            border: 1.5px solid #eab308;
        }

        .rank-2 {
            background: #f1f5f9;
            color: #334155;
            border: 1.5px solid #94a3b8;
        }

        .rank-3 {
            background: #fff7ed;
            color: #7c2d12;
            border: 1.5px solid #f97316;
        }

        .rank-n {
            background: #f3f4f6;
            color: #6b7280;
            border: 1.5px solid #d1d5db;
        }

        .score-bar-wrap {
            width: 80px;
            height: 7px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            display: inline-block;
        }

        .score-bar {
            height: 100%;
            border-radius: 4px;
        }

        .bar-green {
            background: #22c55e;
        }

        .bar-orange {
            background: #f97316;
        }

        .bar-red {
            background: #ef4444;
        }

        .score-val {
            font-size: 13px;
            font-weight: 900;
        }

        .score-green {
            color: #16a34a;
        }

        .score-orange {
            color: #ea580c;
        }

        .score-red {
            color: #dc2626;
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
        <h1>Rekap Performance Score Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }} &mdash; Periode {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; Total karyawan: {{ $stats['total'] }}</div>
    </div>

    {{-- Summary boxes --}}
    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $stats['total'] }}</div>
            <div class="lbl">Total</div>
        </div>
        <div class="summary-box box-yellow">
            <div class="num">{{ number_format($stats['avg_score'], 1) }}</div>
            <div class="lbl">Rata-rata</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ number_format($stats['max_score'], 1) }}</div>
            <div class="lbl">Tertinggi</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ number_format($stats['min_score'], 1) }}</div>
            <div class="lbl">Terendah</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $stats['above_80'] }}</div>
            <div class="lbl">Skor ≥ 80</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $stats['below_50'] }}</div>
            <div class="lbl">Skor &lt; 50</div>
        </div>
    </div>

    <div class="section-title">Peringkat Performance Score</div>

    <table>
        <thead>
            <tr>
                <th style="width:30px;text-align:center">Rank</th>
                <th>Nama Karyawan</th>
                <th>Dept / Posisi</th>
                <th style="width:40px;text-align:center">Target</th>
                <th style="width:45px;text-align:center">Tercapai</th>
                <th style="width:100px;text-align:center">Achievement</th>
                <th style="width:55px;text-align:center">Score</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($scores as $s)
                @php
                    $rate = $s->achievement_rate ?? 0;
                    $score = $s->final_score ?? 0;
                    $rankClass = match ($s->rank) {
                        1 => 'rank-1',
                        2 => 'rank-2',
                        3 => 'rank-3',
                        default => 'rank-n',
                    };
                    $barClass = $rate >= 80 ? 'bar-green' : ($rate >= 50 ? 'bar-orange' : 'bar-red');
                    $scoreClass = $score >= 80 ? 'score-green' : ($score >= 50 ? 'score-orange' : 'score-red');
                    $barWidth = min(100, round($rate)) . '%';
                @endphp
                <tr>
                    <td style="text-align:center">
                        <span class="rank-badge {{ $rankClass }}">{{ $s->rank }}</span>
                    </td>
                    <td><strong>{{ $s->user?->name ?? '-' }}</strong></td>
                    <td>{{ $s->user?->department ?? '-' }} / {{ $s->user?->position ?? '-' }}</td>
                    <td style="text-align:center">{{ $s->total_targets ?? 0 }}</td>
                    <td style="text-align:center">{{ $s->targets_achieved ?? 0 }}</td>
                    <td style="text-align:center">
                        <span class="score-bar-wrap">
                            <span class="score-bar {{ $barClass }}" style="width:{{ $barWidth }}"></span>
                        </span>
                        <span style="margin-left:4px; font-size:9px;">{{ number_format($rate, 1) }}%</span>
                    </td>
                    <td style="text-align:center">
                        <span class="score-val {{ $scoreClass }}">{{ number_format($score, 1) }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Performance Score {{ $periodLabel }} &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
