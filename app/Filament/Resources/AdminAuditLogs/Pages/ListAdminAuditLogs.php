<?php

namespace App\Filament\Resources\AdminAuditLogs\Pages;

use App\Filament\Resources\AdminAuditLogs\AdminAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminAuditLogs extends ListRecords
{
    protected static string $resource = AdminAuditLogResource::class;

    public function getSubheading(): string
    {
        return '관리자가 관리자 패널에 접속하거나 콘텐츠/설정을 생성·수정·삭제할 때마다 자동으로 기록됩니다. 비밀번호 등 민감한 값은 실제 값 대신 "변경됨"으로만 표시됩니다.';
    }
}
