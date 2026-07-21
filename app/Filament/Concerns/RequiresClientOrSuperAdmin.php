<?php

namespace App\Filament\Concerns;

use App\Models\User;

trait RequiresClientOrSuperAdmin
{
    // "운영 관리" 그룹(약관/방침, 관리자 활동로그, AI 대화로그, 유지보수 리포트)처럼 최고관리자와
    // 일반 최고관리자(client)에게는 열어주되 일반관리자(manager)에게는 admin_permissions 체크와
    // 무관하게 항상 막아야 하는 기능에 사용한다.
    public static function canAccess(): bool
    {
        return static::isSuperOrClientAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperOrClientAdmin();
    }

    protected static function isSuperOrClientAdmin(): bool
    {
        $user = auth()->user();

        return $user
            && $user->level === User::LEVEL_ADMIN
            && ($user->admin_role === 'super' || is_null($user->admin_role) || $user->admin_role === 'client');
    }
}
