{{-- 관리자(/admin) 경로에서 발생한 에러용 — Filament/DB에 의존하지 않는 완전히 독립적인
    페이지입니다(장애 상황에서도 이 페이지 자체가 깨지지 않도록 하기 위함). --}}
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body {
            background: #0b0e14; color: #e5e7eb;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0;
        }
        .card { text-align: center; padding: 0 20px; }
        .code { font-size: 0.9rem; color: #9ca3af; letter-spacing: 0.1em; margin: 0; }
        h1 { font-size: 1.5rem; margin: 8px 0 12px; }
        p.message { color: #9ca3af; margin: 0 0 24px; }
        a.btn { display: inline-block; padding: 8px 20px; background: #f97316; color: #fff; border-radius: 6px; text-decoration: none; }
        a.btn:hover { background: #ea580c; }
    </style>
</head>
<body>
    <div class="card">
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p class="message">{{ $message }}</p>
        <a href="{{ url(config('app.admin_path', 'admin')) }}" class="btn">{{ __('관리자 홈으로') }}</a>
    </div>
</body>
</html>
