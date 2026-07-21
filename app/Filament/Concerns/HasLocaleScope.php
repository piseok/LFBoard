<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasLocaleScope
{
    // "담당 언어" 권한(User::localeScope() 참고) — 일반관리자에게 특정 언어가 지정되어 있으면
    // 이 리소스의 모든 화면(목록/검색/폼 조회 등)이 자동으로 그 언어 콘텐츠만 보이도록 스코프한다.
    // 리소스마다 쿼리를 따로 고칠 필요 없이 이 트레이트 하나만 추가하면 된다(locale 컬럼이 있는
    // 리소스에서만 사용 — Board/Page/Menu/Banner/Popup/Inquiry/Policy/EmailTemplate).
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if ($scope = auth()->user()?->localeScope()) {
            $query->whereIn('locale', $scope);
        }

        return $query;
    }
}
