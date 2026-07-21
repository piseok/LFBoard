<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 위쪽 버튼(마이그레이션 실행/캐시 정리/경로 보정/환경 점검) 중 방금 실행한 것의 결과를
             가장 먼저 보여준다 — 이 섹션이 "대기 중인 마이그레이션" 밑에 있으면, 마이그레이션과
             무관한 버튼(캐시 정리, 업로드 환경 점검 등)을 눌러도 마이그레이션 안내 문구가 항상
             먼저 보여서 지금 누른 버튼의 결과가 아닌 것처럼 헷갈렸다. --}}
        @if ($lastOutput)
            <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold">마지막 실행 결과</h3>
                <pre class="mt-4 whitespace-pre-wrap rounded-lg bg-gray-950 p-4 text-xs text-gray-100 overflow-x-auto">{{ $lastOutput }}</pre>
            </div>
        @endif

        {{-- 대기 중인 마이그레이션이 없는 게 대부분이라, 그 상태에서까지 항상 보여주면 다른
             버튼(캐시 정리, 업로드 환경 점검 등)을 눌렀을 때도 매번 같이 떠서 불필요하게
             복잡해 보였다 — 실제로 새 마이그레이션이 있을 때만 보여준다. --}}
        @if ($this->hasPendingMigrations())
            <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold">대기 중인 마이그레이션</h3>
                <p class="text-sm text-gray-500 mt-1">FTP로 새 마이그레이션 파일을 업로드한 뒤 이 목록을 확인하세요.</p>
                <pre class="mt-4 whitespace-pre-wrap rounded-lg bg-gray-950 p-4 text-xs text-gray-100 overflow-x-auto">{{ $lastStatusOutput }}</pre>
            </div>
        @endif
    </div>
</x-filament-panels::page>
