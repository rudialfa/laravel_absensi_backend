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

        .santri-card {
            background: #f0faf4;
            border: 1px solid #c3e6cf;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 12px;
            display: flex;
            gap: 20px;
        }

        .sc-label {
            font-size: 8px;
            color: #888;
            text-transform: uppercase;
        }

        .sc-value {
            font-size: 13px;
            font-weight: 700;
            color: #1a6b3c;
            margin-top: 2px;
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
            font-size: 14px;
            font-weight: 800;
            margin-top: 2px;
        }

        .report-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .rc-header {
            background: #1a6b3c;
            color: #fff;
            padding: 7px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rc-title {
            font-size: 12px;
            font-weight: 700;
        }

        .rc-meta {
            font-size: 9px;
            opacity: .85;
        }

        .rc-body {
            padding: 10px 12px;
        }

        .rc-row {
            display: flex;
            gap: 8px;
            margin-bottom: 6px;
        }

        .rc-col {
            flex: 1;
        }

        .rc-field-label {
            font-size: 8px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .rc-field-value {
            font-size: 10px;
            color: #333;
            line-height: 1.4;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8.5px;
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
        <div>
            <div style="font-size:14px; font-weight:700; color:#1a6b3c;">Laporan Bulanan Santri</div>
            <div class="title">{{ $santri->name }}</div>
            <div class="sub">Periode: {{ $periodLabel }}</div>
        </div>
        <div class="printed">Dicetak: {{ $generatedAt }}</div>
    </div>

    <div class="santri-card">
        <div>
            <div class="sc-label">Nama</div>
            <div class="sc-value" style="font-size:14px;">{{ $santri->name }}</div>
        </div>
        <div>
            <div class="sc-label">Kamar</div>
            <div class="sc-value">{{ $santri->position ?? '-' }}</div>
        </div>
        <div>
            <div class="sc-label">Kelas</div>
            <div class="sc-value">{{ $santri->department ?? '-' }}</div>
        </div>
        <div>
            <div class="sc-label">Email</div>
            <div class="sc-value" style="font-size:11px; color:#555;">{{ $santri->email ?? '-' }}</div>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-label">Total</div>
            <div class="stat-value" style="color:#555;">{{ $stats['total'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Approved</div>
            <div class="stat-value" style="color:#1a6b3c;">{{ $stats['approved'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Submitted</div>
            <div class="stat-value" style="color:#0066cc;">{{ $stats['submitted'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Ditolak</div>
            <div class="stat-value" style="color:#c0392b;">{{ $stats['rejected'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Draft</div>
            <div class="stat-value" style="color:#888;">{{ $stats['draft'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Rata-rata Skor</div>
            <div class="stat-value" style="color:#1a6b3c;">{{ $stats['avg_score'] }}</div>
        </div>
    </div>

    @forelse($reports as $r)
        @php $status = $r->status; @endphp
        <div class="report-card">
            <div class="rc-header">
                <div class="rc-title">Laporan Bulan {{ $r->month }}/{{ $r->year }}</div>
                <div style="display:flex; align-items:center; gap:10px;">
                    @if ($r->score > 0)
                        <span style="font-size:13px; font-weight:800;">Skor: {{ number_format($r->score, 1) }}</span>
                    @endif
                    <span class="badge badge-{{ $status }}">{{ ucfirst($status) }}</span>
                </div>
            </div>
            <div class="rc-body">
                <div class="rc-row">
                    <div class="rc-col">
                        <div class="rc-field-label">Target</div>
                        <div class="rc-field-value">{{ $r->target }}</div>
                    </div>
                    <div class="rc-col">
                        <div class="rc-field-label">Pencapaian</div>
                        <div class="rc-field-value">{{ $r->achievement }}</div>
                    </div>
                </div>
                <div class="rc-row">
                    <div class="rc-col">
                        <div class="rc-field-label">Kendala / Masalah</div>
                        <div class="rc-field-value">{{ $r->problem ?? '-' }}</div>
                    </div>
                    <div class="rc-col">
                        <div class="rc-field-label">Solusi</div>
                        <div class="rc-field-value">{{ $r->solution ?? '-' }}</div>
                    </div>
                </div>
                @if ($r->approver)
                    <div style="margin-top:6px; font-size:9px; color:#888;">
                        Disetujui oleh: <strong>{{ $r->approver->name }}</strong>
                        @if ($r->approved_at)
                            &mdash; {{ \Carbon\Carbon::parse($r->approved_at)->format('d/m/Y H:i') }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p style="color:#aaa; font-style:italic; text-align:center; margin:24px 0;">Tidak ada laporan bulanan.</p>
    @endforelse

    <div class="footer">
        Laporan Bulanan &mdash; {{ $santri->name }} &mdash; {{ $periodLabel }} &mdash; {{ $generatedAt }}
    </div>
</body>

</html>
