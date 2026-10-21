<x-filament-widgets::widget>
    <x-filament::section heading="오늘 AI 사용량" description="우리 사이트의 일일 한도 기준 · 제공자 쪽 크레딧 잔액은 조회할 수 없어 표시하지 않습니다.">
        @include('filament.partials.ai-usage-summary', ['summary' => $summary, 'usage' => $usage])
    </x-filament::section>
</x-filament-widgets::widget>
