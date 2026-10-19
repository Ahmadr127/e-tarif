<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') &middot; @yield('title') | Mapping Tarif</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #ffffff;
            color: #000000;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .page { width: 100%; max-width: 460px; text-align: center; }

        .brand { font-size: 14px; font-weight: 700; letter-spacing: .02em; margin-bottom: 26px; }
        .brand span {
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-right: 8px;
            border-radius: 3px;
            background: #15803d;
            vertical-align: -1px;
        }

        .code { font-size: 56px; font-weight: 800; line-height: 1; color: #15803d; margin: 0 0 14px; }

        h1 { font-size: 20px; font-weight: 700; margin: 0 0 10px; }

        .message { font-size: 14px; line-height: 1.6; color: #333333; margin: 0 auto 28px; max-width: 380px; }

        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }

        .btn {
            display: inline-block;
            padding: 10px 18px;
            border: 1px solid #15803d;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
            text-decoration: none;
            cursor: pointer;
            background: #ffffff;
            color: #15803d;
        }

        .btn:hover { background: #f0fdf4; }

        .btn-primary { background: #15803d; color: #ffffff; }
        .btn-primary:hover { background: #166534; }

        .footer { margin: 24px 0 0; font-size: 12px; color: #666666; }
    </style>
</head>
<body>
    <div class="page">
        <div class="brand"><span></span>Mapping Tarif</div>

        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="message">@yield('message')</p>

        @hasSection('actions')
            <div class="actions">@yield('actions')</div>
        @else
            <div class="actions">
                <a class="btn btn-primary" href="{{ url('/') }}">Kembali ke Beranda</a>
                <button type="button" class="btn" onclick="window.location.reload()">Muat Ulang Halaman</button>
            </div>
        @endif

        <p class="footer">Jika masalah berlanjut, hubungi administrator.</p>
    </div>
</body>
</html>
