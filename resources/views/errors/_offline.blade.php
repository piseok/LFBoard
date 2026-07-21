{{-- 500/503처럼 서버 자체에 문제가 있을 수 있는 상황용 — DB 조회(사이트 설정, 메뉴 등)에
    전혀 의존하지 않는 완전히 정적인 페이지입니다. DB가 실제로 죽은 상황에서 에러 페이지를
    렌더링하다가 또 에러가 나는 사고를 막기 위함입니다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body {
            background: #f7f7f8; color: #1f2937;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0;
        }
        .card { text-align: center; padding: 0 20px; }
        .code { font-size: 0.9rem; color: #9ca3af; letter-spacing: 0.1em; margin: 0; }
        h1 { font-size: 1.5rem; margin: 8px 0 12px; }
        p.message { color: #6b7280; margin: 0; }
    </style>
</head>
<body>
    <div class="card">
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p class="message">{{ $message }}</p>
    </div>
</body>
</html>
