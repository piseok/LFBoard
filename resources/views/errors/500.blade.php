@include('errors._offline', [
    'code' => 500,
    'title' => __('일시적인 오류가 발생했습니다'),
    'message' => __('잠시 후 다시 시도해 주세요.'),
])
