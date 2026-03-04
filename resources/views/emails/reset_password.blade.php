<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: sans-serif;
            background: #f4f4f4;
            padding: 40px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 480px;
            margin: auto;
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: #4f46e5;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin-top: 24px;
        }

        .footer {
            color: #999;
            font-size: 12px;
            margin-top: 24px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>🔐 Reset Password</h2>
        <p>Halo <strong>{{ $user->name }}</strong>,</p>
        <p>Kami menerima permintaan reset password untuk akun kamu.</p>
        <p>Klik tombol di bawah. Link berlaku <strong>60 menit</strong>.</p>
        <a href="{{ $resetUrl }}" class="btn">Reset Password Sekarang</a>
        <p class="footer">Jika tidak merasa request ini, abaikan email ini.</p>
    </div>
</body>

</html>
