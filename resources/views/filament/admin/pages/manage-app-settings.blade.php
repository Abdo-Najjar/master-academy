<x-filament-panels::page>
    <form wire:submit="save">
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-6">
                {{ $this->form }}
            </div>

            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-x-3 px-6 py-4 border-t border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-gray-900 rounded-b-xl">
                <x-filament::button type="submit" size="lg" icon="heroicon-o-check" wire:target="save">
                    {{ __('Save Changes') }}
                </x-filament::button>
            </div>
        </div>
    </form>
</x-filament-panels::page>
