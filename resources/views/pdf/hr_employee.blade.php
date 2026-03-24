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

        .box-green {
            background: #d1fae5;
            color: #065f46;
        }

        .dept-title {
            font-size: 11px;
            font-weight: 700;
            color: #1a1a2e;
            background: #f1f5f9;
            padding: 7px 20px;
            margin: 12px 0 0;
            border-left: 4px solid #1a1a2e;
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
        }

        tr:nth-child(even) td {
            background: #f8f9fa;
        }

        .avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #dbeafe;
            color: #1e40af;
            font-size: 9px;
            font-weight: 900;
            vertical-align: middle;
            margin-right: 6px;
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
        <h1>Daftar Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }}</p>
        <div class="meta">
            Dicetak: {{ $generatedAt }} &nbsp;|&nbsp;
            Total karyawan: {{ $total }} &nbsp;|&nbsp;
            Total departemen: {{ $byDept->count() }}
        </div>
    </div>

    {{-- Summary --}}
    <div class="summary-row">
        <div class="summary-box box-blue">
            <div class="num">{{ $total }}</div>
            <div class="lbl">Total Karyawan</div>
        </div>
        <div class="summary-box box-green">
            <div class="num">{{ $byDept->count() }}</div>
            <div class="lbl">Departemen</div>
        </div>
    </div>

    {{-- Per departemen --}}
    @foreach ($byDept as $dept => $empList)
        <div class="dept-title">{{ $dept }} ({{ $empList->count() }} karyawan)</div>

        <table style="margin-bottom:4px;">
            <thead>
                <tr>
                    <th style="width:22px">No</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Posisi</th>
                    <th style="width:65px">Bergabung</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($empList as $i => $emp)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <span class="avatar">{{ strtoupper(substr($emp->name ?? 'U', 0, 1)) }}</span>
                            <strong>{{ $emp->name ?? '-' }}</strong>
                        </td>
                        <td>{{ $emp->email ?? '-' }}</td>
                        <td>{{ $emp->phone ?? '-' }}</td>
                        <td>{{ $emp->position ?? '-' }}</td>
                        <td>{{ $emp->created_at ? \Carbon\Carbon::parse($emp->created_at)->format('d/m/Y') : '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        Daftar Karyawan &mdash; {{ $company->name ?? '' }} &mdash; {{ $generatedAt }}
    </div>

</body>

</html>
