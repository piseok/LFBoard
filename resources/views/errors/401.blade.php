@include('errors._page', [
    'code' => 401,
    'title' => __('로그인이 필요합니다'),
    'message' => ($exception->getMessage() ?: null) ?? __('이 페이지는 로그인 후 이용하실 수 있습니다.'),
])
