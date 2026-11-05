<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Sedang Perbaikan — {{ $appName }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #F7F8F4;
            color: #10233A;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .box {
            width: 100%;
            max-width: 520px;
            background: #FFFFFF;
            border: 1px solid #E2E6E1;
            border-radius: 20px;
            padding: 40px 32px;
            text-align: center;
        }

        .icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            border-radius: 18px;
            background: #FFF4DE;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 24px;
        }

        p {
            margin: 0 0 16px;
            line-height: 1.6;
            color: #4A5B6C;
        }

        .until {
            display: inline-block;
            margin-bottom: 16px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #E3F1EC;
            color: #0A5C4C;
            font-weight: 600;
            font-size: 14px;
        }

        .contact {
            font-size: 14px;
            color: #4A5B6C;
        }

        .contact a {
            color: #0B6E5B;
            font-weight: 600;
        }

        .brand {
            margin-top: 28px;
            font-size: 13px;
            color: #6B7A88;
        }
    </style>
</head>

<body>
    <main class="box">
        <div class="icon" aria-hidden="true">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#B45309" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z">
                </path>
            </svg>
        </div>
        <h1>Sedang dalam perbaikan</h1>
        <p>{{ $message }}</p>

        @if ($until && $until->isFuture())
            <div class="until">Perkiraan selesai: {{ $until->translatedFormat('l, d M Y H:i') }} WIB</div>
        @endif

        @if ($support)
            <div class="contact">Butuh bantuan? Hubungi <a href="mailto:{{ $support }}">{{ $support }}</a>
            </div>
        @endif

        <div class="brand">{{ $appName }}</div>
    </main>
</body>

</html>
