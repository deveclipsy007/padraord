<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ data_get($release, 'document.title') }} · Padrão RD</title>
    <style>
        :root { color: #1d1d1f; background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", sans-serif; }
        body { max-width: 840px; margin: 0 auto; padding: 40px 20px; }
        main { padding: clamp(24px, 6vw, 64px); border: 1px solid #d5d5d7; border-radius: 18px; background: #fff; box-shadow: 0 18px 42px rgba(0, 0, 0, .08); }
        @media (max-width: 640px) { body { padding: 12px; } main { border-radius: 14px; } }
    </style>
</head>
<body>
    <main>{!! $release['html'] !!}</main>
</body>
</html>
