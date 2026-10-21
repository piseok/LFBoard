<?php

namespace App\Services\Ai;

use RuntimeException;

// 메시지를 관리자에게 그대로 보여 줘도 안전한 예외(서버가 직접 쓴 안내 문구만 담는다). 위젯은 이 계열만 메시지를 보여 주고,
// 그 밖의 예외(DB 오류·QueryException·ModelNotFound 등 RuntimeException 이라도)는 report() 후 일반 문구로 바꾼다 -
// 예전에는 위젯이 RuntimeException 전체를 잡아 SQL 문구가 채팅창에 그대로 나올 수 있었다(리뷰 M2).
class AiUserFacingException extends RuntimeException {}
