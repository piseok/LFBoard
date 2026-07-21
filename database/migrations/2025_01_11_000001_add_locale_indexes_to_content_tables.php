<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 다국어(D 섹션) 작업 이후 회귀/보완 점검 중 발견 — pages/boards/policies/email_templates는
// (locale, slug) 또는 (type, locale) 복합 unique 인덱스가 이미 있어 locale 단독 조회도 그 인덱스를
// 재사용할 수 있지만, menus/banners/popups/inquiries는 locale 컬럼을 추가할 때 인덱스를 전혀
// 만들지 않았다. 이 중 menus(메뉴 트리)/popups(팝업)/banners(메인 배너)는 방문자 화면 로드마다
// 매번 locale로 필터링되는 조회라(MenuService::getTree()/PopupService::getActive()/
// FrontController::index()) 데이터가 늘어나면 영향이 커지는 지점 — 지금은 데이터량이 적어 체감되는
// 문제는 아니지만, 인덱스 추가 자체는 순수 추가라 위험이 없어 미리 보완해 둔다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->index(['locale', 'is_active'], 'menus_locale_is_active_index');
        });

        Schema::table('popups', function (Blueprint $table) {
            $table->index(['locale', 'is_active'], 'popups_locale_is_active_index');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->index(['group_key', 'locale', 'is_active'], 'banners_group_key_locale_is_active_index');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->index('locale', 'inquiries_locale_index');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropIndex('menus_locale_is_active_index');
        });

        Schema::table('popups', function (Blueprint $table) {
            $table->dropIndex('popups_locale_is_active_index');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex('banners_group_key_locale_is_active_index');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropIndex('inquiries_locale_index');
        });
    }
};
