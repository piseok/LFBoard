<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    use CancelsToListPage;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    // CreateUser::mutateFormDataBeforeCreate()와 동일한 이유 — 수정 시에도 제출된 admin_role이
    // 현재 로그인한 관리자가 부여할 수 있는 역할인지 서버에서 다시 검증해야 한다.
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['admin_role'] ?? null) && ! array_key_exists($data['admin_role'], UserResource::allowedAdminRoleOptions())) {
            throw ValidationException::withMessages([
                'data.admin_role' => '이 관리자 역할을 부여할 권한이 없습니다.',
            ]);
        }

        return $data;
    }
}
