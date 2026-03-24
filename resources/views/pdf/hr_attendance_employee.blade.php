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

        .employee-box {
            margin: 0 20px 14px;
            padding: 12px 14px;
            background: #f1f5f9;
            border-radius: 8px;
            border-left: 4px solid #1a1a2e;
        }

        .employee-box .name {
            font-size: 14px;
            font-weight: 800;
        }

        .employee-box .info {
            font-size: 10px;
            color: #555;
            margin-top: 3px;
        }

        .summary-row {
            display: flex;
            margin: 0 20px 14px;
            gap: 8px;
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

        .box-green {
            background: #d1fae5;
            color: #065f46;
        }

        .box-blue {
            background: #dbeafe;
            color: #1e40af;
        }

        .box-orange {
            background: #fef3c7;
            color: #92400e;
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
            margin: 14px 20px 8px;
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
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
        }

        .badge-on-time {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-late {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-other {
            background: #f3f4f6;
            color: #374151;
        }

        .info-red {
            color: #dc2626;
            font-size: 8.5px;
            font-weight: 600;
        }

        .info-orange {
            color: #d97706;
            font-size: 8.5px;
            font-weight: 600;
        }

        .total-note {
            margin: 10px 20px;
            padding: 10px 14px;
            background: #fef9ec;
            border-radius: 8px;
            border: 1px solid #f59e0b;
            font-size: 10px;
            color: #92400e;
            font-weight: 600;
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
        <h1>Riwayat Absensi Karyawan</h1>
        <p>{{ $company->name ?? 'Perusahaan' }} &mdash; Periode {{ $periodLabel }}</p>
        <div class="meta">Dicetak: {{ $generatedAt }}</div>
    </div>

    <div class="employee-box">
        <div class="name">{{ $employee->name }}</div>
        <div class="info">
            {{ $employee->position ?? '-' }}
            @if ($employee->department)
                &nbsp;&bull;&nbsp; {{ $employee->department }}
            @endif
        </div>
    </div>

    <div class="summary-row">
        <div class="summary-box box-green">
            <div class="num">{{ $hadir }}</div>
            <div class="lbl">Hadir</div>
        </div>
        <div class="summary-box box-blue">
            <div class="num">{{ $onTime }}</div>
            <div class="lbl">Tepat Waktu</div>
        </div>
        <div class="summary-box box-orange">
            <div class="num">{{ $late }}</div>
            <div class="lbl">Terlambat</div>
        </div>
        <div class="summary-box box-red">
            <div class="num">{{ $absent }}</div>
            <div class="lbl">Tidak Hadir</div>
        </div>
    </div>

    <div class="section-title">Detail Absensi Harian</div>

    <table>
        <thead>
            <tr>
                <th style="width:70px">Tanggal</th>
                <th style="width:55px">Shift</th>
                <th style="width:50px">Jadwal In</th>
                <th style="width:50px">Jadwal Out</th>
                <th style="width:50px">Check-In</th>
                <th style="width:50px">Check-Out</th>
                <th style="width:65px">Status</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($attendances as $a)
                @php
                    $statusClass = match ($a->status) {
                        'on_time' => 'badge-on-time',
                        'late' => 'badge-late',
                        'absent' => 'badge-absent',
                        default => 'badge-other',
                    };
                    $statusLabel = match ($a->status) {
                        'on_time' => 'Tepat Waktu',
                        'late' => 'Terlambat',
                        'absent' => 'Tidak Hadir',
                        'permission' => 'Izin',
                        'overtime' => 'Lembur',
                        default => ucfirst($a->status ?? '-'),
                    };
                @endphp
                <tr>
                    <td>{{ $a->date }}</td>
                    <td>{{ $a->shift?->name ?? '-' }}</td>
                    <td>{{ $a->scheduled_in ? substr($a->scheduled_in, 0, 5) : '-' }}</td>
                    <td>{{ $a->scheduled_out ? substr($a->scheduled_out, 0, 5) : '-' }}</td>
                    <td><strong>{{ $a->time_in ? substr($a->time_in, 0, 5) : '-' }}</strong></td>
                    <td><strong>{{ $a->time_out ? substr($a->time_out, 0, 5) : '-' }}</strong></td>
                    <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                    <td>
                        @if ($a->late_minutes > 0)
                            <span class="info-red">Terlambat {{ $a->late_minutes }} mnt</span>
                        @endif
                        @if ($a->early_leave_minutes > 0)
                            @if ($a->late_minutes > 0)
                                <br>
                            @endif
                            <span class="info-orange">Pulang awal {{ $a->early_leave_minutes }} mnt</span>
                        @endif
                        @if ($a->late_minutes == 0 && $a->early_leave_minutes == 0)
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($totalLate > 0 || $totalEarly > 0)
        <div class="total-note">
            Total terlambat: {{ $totalLate }} menit
            @if ($totalEarly > 0)
                &nbsp;&bull;&nbsp; Total pulang awal: {{ $totalEarly }} menit
            @endif
        </div>
    @endif

    <div class="footer">
        Absensi {{ $employee->name }} &mdash; {{ $periodLabel }} &mdash; {{ $company->name ?? '' }} &mdash;
        {{ $generatedAt }}
    </div>

</body>

</html>
