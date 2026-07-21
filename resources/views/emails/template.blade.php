<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    /* 인라인 스타일 — 이메일 클라이언트 호환성 위해 외부 CSS 사용 금지 */
    body { font-family: sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
    .wrap { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
    .header { background: #1a1a2e; color: #fff; padding: 24px; text-align: center; }
    .body { padding: 32px; color: #333; line-height: 1.7; }
    .footer { background: #f4f4f4; padding: 16px; text-align: center; font-size: 12px; color: #999; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="header">{{ $siteName }}</div>
    <div class="body">{!! $body !!}</div>
    <div class="footer">{{ __('본 메일은 발신 전용입니다.') }}</div>
  </div>
</body>
</html>
