<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // Select의 options()는 화면 표시일 뿐 제출값 자체를 막지 않는다 — 폼 조작으로 허용되지 않은
    // admin_role(예: 일반 최고관리자가 슈퍼관리자를 부여)을 보내는 것을 서버에서도 반드시 막아야 한다.
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($data['admin_role'] ?? null) && ! array_key_exists($data['admin_role'], UserResource::allowedAdminRoleOptions())) {
            throw ValidationException::withMessages([
                'data.admin_role' => '이 관리자 역할을 부여할 권한이 없습니다.',
            ]);
        }

        return $data;
    }
}
