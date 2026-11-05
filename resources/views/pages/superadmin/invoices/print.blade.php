<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #222;
            margin: 0;
            background: #f2f3f5;
            font-size: 14px;
        }

        .page {
            max-width: 780px;
            margin: 24px auto;
            background: #fff;
            padding: 40px;
            border-radius: 6px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 26px;
            letter-spacing: 1px;
        }

        .muted {
            color: #6c757d;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
        }

        .paid {
            background: #d4edda;
            color: #155724;
        }

        .pending {
            background: #fff3cd;
            color: #856404;
        }

        .expired,
        .cancelled {
            background: #e2e3e5;
            color: #383d41;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }

        th,
        td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }

        th {
            background: #f7f7f9;
            font-size: 12px;
            text-transform: uppercase;
            color: #555;
        }

        .right {
            text-align: right;
        }

        .total td {
            font-weight: 700;
            font-size: 16px;
            border-bottom: none;
        }

        .box {
            background: #f7f7f9;
            border-radius: 6px;
            padding: 14px 16px;
            margin-top: 24px;
        }

        .toolbar {
            max-width: 780px;
            margin: 16px auto 0;
            text-align: right;
        }

        .toolbar button {
            padding: 8px 16px;
            border: 0;
            background: #6777ef;
            color: #fff;
            border-radius: 4px;
            cursor: pointer;
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                margin: 0;
                padding: 0;
                max-width: none;
            }

            .toolbar {
                display: none;
            }
        }
    </style>
</head>

<body>
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $company = $invoice->company;
    @endphp

    <div class="toolbar">
        <button onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <div class="page">
        <div class="row">
            <div>
                <h1>INVOICE</h1>
                <div class="muted">{{ $invoice->invoice_number }}</div>
            </div>
            <div style="text-align:right">
                <strong>{{ config('app.name') }}</strong><br>
                <span class="muted">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</span><br>
                <span class="badge {{ $invoice->status }}"
                    style="margin-top:8px">{{ $statuses[$invoice->status] ?? $invoice->status }}</span>
            </div>
        </div>

        <div class="row" style="margin-top:28px">
            <div>
                <div class="muted" style="font-size:12px">DITAGIHKAN KEPADA</div>
                <strong>{{ $company->name ?? '-' }}</strong><br>
                {{ $company->email ?? '' }}<br>
                <span
                    class="muted">{{ $company->address ?? '' }}{{ $company && $company->city ? ', ' . $company->city : '' }}</span>
            </div>
            <div style="text-align:right">
                <div><span class="muted">Tanggal:</span> {{ optional($invoice->issued_at)->format('d M Y') }}</div>
                <div><span class="muted">Jatuh tempo:</span> {{ optional($invoice->due_at)->format('d M Y H:i') }}
                </div>
                @if ($invoice->paid_at)
                    <div><span class="muted">Dibayar:</span> {{ $invoice->paid_at->format('d M Y H:i') }}</div>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th class="right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        Langganan {{ $invoice->plan->name ?? '-' }}
                        <div class="muted" style="font-size:12px">Masa aktif
                            {{ $invoice->plan->duration_days ?? '-' }} hari</div>
                    </td>
                    <td class="right">{{ $rupiah($invoice->subtotal) }}</td>
                </tr>
                @if ((float) $invoice->discount_amount > 0)
                    <tr>
                        <td>Diskon
                            {{ $invoice->discount ? '(' . ($invoice->discount->code ?? $invoice->discount->name) . ')' : '' }}
                        </td>
                        <td class="right">−{{ $rupiah($invoice->discount_amount) }}</td>
                    </tr>
                @endif
                <tr class="total">
                    <td class="right">Total</td>
                    <td class="right">{{ $rupiah($invoice->total_amount) }}</td>
                </tr>
            </tbody>
        </table>

        @if ($invoice->vaPayment && $invoice->status === 'pending')
            <div class="box">
                <strong>Pembayaran via Virtual Account {{ strtoupper($invoice->vaPayment->bank) }}</strong><br>
                No. VA: <strong
                    style="font-size:16px; letter-spacing:1px">{{ $invoice->vaPayment->va_number }}</strong><br>
                <span class="muted">Berlaku sampai
                    {{ optional($invoice->vaPayment->expired_at)->format('d M Y H:i') }}</span>
            </div>
        @endif

        <p class="muted" style="margin-top:32px; font-size:12px">
            Invoice ini dibuat secara elektronik dan sah tanpa tanda tangan.
        </p>
    </div>
</body>

</html>
