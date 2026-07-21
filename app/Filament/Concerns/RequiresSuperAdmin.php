<?php

namespace App\Filament\Concerns;

use App\Models\User;

trait RequiresSuperAdmin
{
    // 이 trait을 사용하는 Resource/Page는 admin_permissions 체크와 무관하게
    // 항상 최고관리자(super)만 접근/조회 가능하다 — 시스템 설정 그룹처럼 위험도가 높은 기능에 사용한다.
    public static function canAccess(): bool
    {
        return static::isSuperAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperAdmin();
    }

    protected static function isSuperAdmin(): bool
    {
        $user = auth()->user();

        return $user
            && $user->level === User::LEVEL_ADMIN
            && ($user->admin_role === 'super' || is_null($user->admin_role));
    }
}
