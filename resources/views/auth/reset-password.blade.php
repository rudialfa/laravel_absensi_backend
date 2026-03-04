<!DOCTYPE html>
<html>

<head>
    <title>Reset Password</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            margin-bottom: 4px;
            color: #1a1a1a;
        }

        p {
            color: #666;
            font-size: 14px;
            margin-bottom: 24px;
        }

        label {
            font-size: 13px;
            font-weight: 600;
            color: #444;
            display: block;
            margin-bottom: 4px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
            outline: none;
        }

        input:focus {
            border-color: #4f46e5;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 4px;
        }

        button:hover {
            background: #4338ca;
        }

        .error {
            background: #fff0f0;
            color: #e53e3e;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .success {
            background: #f0fff4;
            color: #38a169;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>🔐 Reset Password</h2>
        <p>Masukkan password baru untuk akun <strong>{{ $email }}</strong></p>

        @if (isset($error))
            <div class="error">{{ $error }}</div>
        @endif

        <form method="POST" action="/api/reset-form">
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <label>Password Baru</label>
            <input type="password" name="password" placeholder="Minimal 6 karakter" required>

            <label>Konfirmasi Password</label>
            <input type="password" name="password_confirmation" placeholder="Ulangi password" required>

            <button type="submit">Reset Password</button>
        </form>
    </div>
</body>

</html>
