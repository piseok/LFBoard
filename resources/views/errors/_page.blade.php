{{-- 클라이언트 쪽 에러(404/403/401/419/429)를 관리자/방문자 화면 중 어느 쪽에서 보여줄지
    나눠주는 공용 뷰입니다. 500/503처럼 서버 자체가 문제인 상황은 DB 조회가 필요한 헤더/푸터에
    기대면 안 되므로 여기 포함하지 않고 완전히 독립된 뷰를 따로 씁니다. --}}
@php
    $adminPath = config('app.admin_path', 'admin');
    $isAdmin = request()->is($adminPath.'/*') || request()->is($adminPath);
@endphp

@include($isAdmin ? 'errors._admin' : 'errors._front', ['code' => $code, 'title' => $title, 'message' => $message])
