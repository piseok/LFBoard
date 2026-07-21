<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold">최근 에러 로그</h3>
            <p class="text-sm text-gray-500 mt-1">
                {{ $logPath }} 파일의 최근 내용입니다(최신 항목이 위에 표시됩니다). 서버에 직접 접속하지 않아도 여기서 확인할 수 있습니다.
            </p>
            <pre class="mt-4 whitespace-pre-wrap rounded-lg bg-gray-950 p-4 text-xs text-gray-100 overflow-x-auto max-h-[70vh] overflow-y-auto">{{ $logContent }}</pre>
        </div>
    </div>
</x-filament-panels::page>
