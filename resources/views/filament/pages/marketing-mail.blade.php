<x-filament-panels::page>
    <form wire:submit="send">
        {{ $this->form }}

        <div class="mt-4 text-sm text-warning-600">
            발송 대상 {{ $this->recipientCount() }}명에게 발송됩니다. 발송 중 페이지를 닫지 마세요.
        </div>

        <div class="mt-4">
            <x-filament::button
                type="submit"
                color="danger"
                wire:confirm="발송 대상 {{ $this->recipientCount() }}명에게 메일을 발송합니다. 계속하시겠습니까?"
            >
                발송
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
