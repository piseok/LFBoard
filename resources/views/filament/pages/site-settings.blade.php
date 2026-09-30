<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6" style="margin-top: 1.25rem;">
            <x-filament::button type="submit">
                저장
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
