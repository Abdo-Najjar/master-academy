<x-filament-panels::page>
    <form wire:submit="save">
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            {{ $this->form }}

            <div style="display:flex; justify-content:flex-start; gap:0.5rem;">
                <x-filament::button type="submit" size="lg" icon="heroicon-o-check" wire:target="save">
                    {{ __('Enroll Student') }}
                </x-filament::button>
                <x-filament::button color="gray" tag="a" href="{{ url('admin') }}" size="lg">
                    {{ __('Cancel') }}
                </x-filament::button>
            </div>
        </div>
    </form>
</x-filament-panels::page>
