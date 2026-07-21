<?php

namespace App\Filament\Concerns;

trait HasPermissionCheck
{
    // 이 trait을 사용하는 Resource/Page는 반드시 아래 프로퍼티를 선언해야 한다:
    // protected static string $permissionKey = 'xxx'; (admin_permissions JSON의 키)

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || $user->level !== \App\Models\User::LEVEL_ADMIN) {
            return false;
        }

        return $user->hasAdminPermission(static::$permissionKey);
    }
}
