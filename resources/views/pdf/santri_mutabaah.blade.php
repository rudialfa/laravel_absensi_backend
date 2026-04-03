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

        .santri-card {
            background: #f0faf4;
            border: 1px solid #c3e6cf;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
            display: flex;
            gap: 20px;
            align-items: center;
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

        .ringkasan-section {
            margin-bottom: 14px;
        }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #1a6b3c;
            margin-bottom: 6px;
            border-left: 3px solid #1a6b3c;
            padding-left: 8px;
        }

        .ringkasan-grid {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .jilid-box {
            border: 1px solid #c3e6cf;
            border-radius: 5px;
            padding: 6px 10px;
            min-width: 80px;
            text-align: center;
            background: #fafff9;
        }

        .jilid-label {
            font-size: 8px;
            color: #888;
            text-transform: uppercase;
        }

        .jilid-value {
            font-size: 13px;
            font-weight: 700;
            color: #1a6b3c;
        }

        .jilid-sub {
            font-size: 8px;
            color: #888;
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
            padding: 4px 7px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        tr:nth-child(even) td {
            background: #fafafa;
        }

        .nilai {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
        }

        .n-Ap {
            background: #e8f5ee;
            color: #155724;
        }

        .n-A {
            background: #d4edda;
            color: #155724;
        }

        .n-Am {
            background: #d4edda;
            color: #155724;
        }

        .n-Bp {
            background: #cce5ff;
            color: #004085;
        }

        .n-B {
            background: #cce5ff;
            color: #004085;
        }

        .n-Bm {
            background: #fff3cd;
            color: #856404;
        }

        .n-Cp {
            background: #fff3cd;
            color: #856404;
        }

        .n-C {
            background: #ffe8cc;
            color: #7a3f00;
        }

        .n-Cm {
            background: #f8d7da;
            color: #721c24;
        }

        .n-Dp {
            background: #f8d7da;
            color: #721c24;
        }

        .n-D {
            background: #f5c6cb;
            color: #721c24;
        }

        .n-Dm {
            background: #f5c6cb;
            color: #721c24;
        }

        .lanjut-ya {
            color: #1a6b3c;
            font-weight: 700;
        }

        .lanjut-ulang {
            color: #c0392b;
            font-weight: 700;
        }

        .paraf-ya {
            color: #1a6b3c;
        }

        .paraf-no {
            color: #aaa;
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
        @if (!empty($company->image_url))
            <img class="logo" src="{{ public_path($company->image_url) }}" alt="Logo">
        @endif
        <div>
            <div class="company-name">{{ $company->name ?? 'Pesantren' }}</div>
            <div class="title">Kartu Prestasi Ngaji</div>
            <div class="sub">Rekap riwayat ngaji santri</div>
        </div>
        <div class="printed">Dicetak: {{ $generatedAt }}</div>
    </div>

    {{-- INFO SANTRI --}}
    <div class="santri-card">
        <div>
            <div class="sc-label">Nama Santri</div>
            <div class="sc-value" style="font-size:15px;">{{ $santri->name }}</div>
        </div>
        <div>
            <div class="sc-label">Kamar</div>
            <div class="sc-value">{{ $santri->position ?? '-' }}</div>
        </div>
        <div>
            <div class="sc-label">Kelas / Angkatan</div>
            <div class="sc-value">{{ $santri->department ?? '-' }}</div>
        </div>
        <div>
            <div class="sc-label">Total Sesi</div>
            <div class="sc-value" style="font-size:18px;">{{ $records->count() }}</div>
        </div>
    </div>

    {{-- RINGKASAN PER JILID --}}
    @if ($ringkasanJilid->isNotEmpty())
        <div class="ringkasan-section">
            <div class="section-title">Ringkasan Per Jilid</div>
            <div class="ringkasan-grid">
                @foreach ($ringkasanJilid as $rj)
                    <div class="jilid-box">
                        <div class="jilid-label">{{ ucfirst($rj->kitab) }} Jilid {{ $rj->jilid }}</div>
                        <div class="jilid-value">{{ $rj->total_sesi }}</div>
                        <div class="jilid-sub">sesi</div>
                        <div class="jilid-sub" style="color:#1a6b3c; font-weight:600;">{{ $rj->total_lanjut }}× lanjut
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- RIWAYAT NGAJI --}}
    <div class="section-title" style="margin-bottom:8px;">Riwayat Sesi Ngaji</div>
    <table>
        <thead>
            <tr>
                <th style="width:24px;">#</th>
                <th style="width:68px;">Tanggal</th>
                <th style="width:45px; text-align:center;">Sesi</th>
                <th>Posisi Bacaan</th>
                <th style="width:90px;">Halaman</th>
                <th style="width:42px; text-align:center;">Nilai</th>
                <th style="width:60px; text-align:center;">Status</th>
                <th>Ustadz</th>
                <th style="width:50px; text-align:center;">Paraf</th>
                <th style="width:90px;">Penandatangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $i => $r)
                @php
                    $nilaiClass = 'n-' . str_replace(['+', '-'], ['p', 'm'], $r->keterangan);
                    $halaman = 'Hal. ' . $r->halaman_dari;
                    if ($r->halaman_sampai && $r->halaman_sampai !== $r->halaman_dari) {
                        $halaman .= '–' . $r->halaman_sampai;
                    }
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->tanggal->format('d/m/Y') }}</td>
                    <td style="text-align:center;">{{ ucfirst($r->sesi) }}</td>
                    <td style="font-weight:600;">{{ ucfirst($r->kitab) }} Jilid {{ $r->jilid }}</td>
                    <td>{{ $halaman }}</td>
                    <td style="text-align:center;">
                        <span class="nilai {{ $nilaiClass }}">{{ $r->keterangan }}</span>
                    </td>
                    <td style="text-align:center;">
                        @if ($r->is_lanjut)
                            <span class="lanjut-ya">↑ Lanjut</span>
                        @else
                            <span class="lanjut-ulang">↺ Ulang</span>
                        @endif
                    </td>
                    <td style="font-size:9px;">{{ $r->ustadz?->name ?? '-' }}</td>
                    <td style="text-align:center;">
                        @if ($r->signed_by)
                            <span class="paraf-ya">✓ Sudah</span>
                        @else
                            <span class="paraf-no">–</span>
                        @endif
                    </td>
                    <td style="font-size:9px; color:#555;">
                        {{ $r->penandatangan?->name ?? '-' }}
                        @if ($r->signed_at)
                            <br><span
                                style="font-size:8px; color:#aaa;">{{ \Carbon\Carbon::parse($r->signed_at)->format('d/m') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center; color:#aaa; font-style:italic; padding:20px;">
                        Tidak ada riwayat ngaji.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- KETERANGAN NILAI --}}
    <div style="margin-top:12px; border:1px solid #eee; border-radius:5px; padding:8px 12px; font-size:8px;">
        <strong style="color:#1a6b3c;">Keterangan Nilai:</strong>
        <span style="margin-left:8px;">
            <strong>Lanjut (↑)</strong>: A+, A, A-, B+, B &nbsp;|&nbsp;
            <strong>Ulang (↺)</strong>: B-, C+, C, C-, D+, D, D-
        </span>
    </div>

    <div class="footer">
        {{ $company->name ?? 'Pesantren' }} &mdash; Kartu Prestasi Ngaji &mdash; {{ $santri->name }} &mdash;
        {{ $generatedAt }}
    </div>
</body>

</html>
