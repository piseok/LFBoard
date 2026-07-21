<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-semibold text-danger-600 mb-4">
                ⚠ 이 화면은 데이터베이스에 SQL을 직접 실행합니다. 잘못 실행하면 데이터가 영구적으로
                손상되거나 삭제될 수 있으며 되돌릴 수 없습니다. 실행 전 반드시 백업 상태를 확인하세요.
            </p>

            <form wire:submit="run">
                {{ $this->form }}

                <div class="mt-4">
                    <x-filament::button
                        type="submit"
                        color="danger"
                        wire:confirm="이 SQL을 실행하시겠습니까? 되돌릴 수 없습니다."
                    >
                        실행
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if ($errorMessage)
            <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold text-danger-600">오류</h3>
                <pre class="mt-4 whitespace-pre-wrap rounded-lg bg-gray-950 p-4 text-xs text-red-300 overflow-x-auto">{{ $errorMessage }}</pre>
            </div>
        @endif

        @if ($resultHtml)
            <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold">결과</h3>
                <div class="mt-4">{!! $resultHtml !!}</div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
