<?php

namespace App\Services\Ai;

// 같은 관리자의 이전 AI 요청이 아직 처리 중이다(관리자별 동시 1건 가드) - 제공자는 호출하지 않았다.
class AiBusyException extends AiUserFacingException {}
