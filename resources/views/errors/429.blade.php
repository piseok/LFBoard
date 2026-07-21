@include('errors._page', [
    'code' => 429,
    'title' => __('요청이 너무 많습니다'),
    'message' => __('잠시 후 다시 시도해 주세요.'),
])
