<?php

namespace App\Services;

use App\Models\Board;
use App\Models\Language;
use App\Models\Page;
use Illuminate\Http\Request;

class LocaleSwitchService
{
    /**
     * 헤더 언어 전환 버튼용 — 활성 언어별로 "지금 보고 있는 화면과 같은 slug"가 그 언어에도
     * 있으면 그 화면으로, 없으면 그 언어의 홈으로 연결한다(같은 slug를 언어 간에 재사용하는
     * pages/boards 설계 규칙을 그대로 활용, 별도 번역 매핑 테이블 불필요).
     *
     * @return array<int, array{code: string, name: string, url: string, is_current: bool}>
     */
    public function links(Request $request): array
    {
        $currentLocale = app()->getLocale();
        $routeName = $request->route()?->getName() ?? 'home';
        $currentPrefix = Language::routeNamePrefix($currentLocale);
        $baseName = ($currentPrefix !== '' && str_starts_with($routeName, $currentPrefix))
            ? substr($routeName, strlen($currentPrefix))
            : $routeName;
        $slug = $request->route('slug');

        return Language::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Language $language) => [
                'code' => $language->code,
                'name' => $language->name,
                'url' => $this->resolveUrl($baseName, $slug, $language->code),
                'is_current' => $language->code === $currentLocale,
            ])
            ->all();
    }

    private function resolveUrl(string $baseName, ?string $slug, string $targetLocale): string
    {
        $prefix = Language::routeNamePrefix($targetLocale);

        if ($slug) {
            if ($baseName === 'page.show' && Page::query()->where('slug', $slug)->where('locale', $targetLocale)->exists()) {
                return route($prefix.'page.show', ['slug' => $slug]);
            }

            if (str_starts_with($baseName, 'board.') && Board::query()->where('slug', $slug)->where('locale', $targetLocale)->exists()) {
                return route($prefix.'board.index', ['slug' => $slug]);
            }
        }

        return route($prefix.'home');
    }
}
