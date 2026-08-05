{{-- Floating toast for the student/trainer portals, mirroring the Filament admin
     notification so a success message is visible even when the user is scrolled
     to the bottom of a long form (e.g. the attendance roster). Driven by the
     `portal-toast` browser event dispatched from Livewire components.

     Included from the layout, outside any Livewire root: the toasts are rendered
     by Alpine's `x-for` and are absent from the server HTML, so a morph would
     strip them right after the update that asked for the toast (`wire:ignore` is
     not enough — Alpine's tree still gets torn down).

     The handler also has to stay a single call expression: Alpine only wraps an
     expression in an IIFE when it *begins* with `let`/`const`, so an indented
     multi-statement handler would be evaluated as `return const …` and throw. --}}
<div x-data="{
        toasts: [],
        next: 1,
        add(detail) {
            const id = this.next++;
            this.toasts.push({ id, message: detail.message, type: detail.type || 'success' });
            setTimeout(() => this.remove(id), 4000);
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
     }"
     @portal-toast.window="add($event.detail)"
     class="fixed top-4 end-4 z-[60] flex flex-col gap-2 pointer-events-none w-[min(22rem,calc(100vw-2rem))]">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 shadow-lg dark:bg-gray-800"
             :class="toast.type === 'danger'
                ? 'border-red-200 dark:border-red-900/50'
                : 'border-emerald-200 dark:border-emerald-900/50'">
            <template x-if="toast.type !== 'danger'">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </template>
            <template x-if="toast.type === 'danger'">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/>
                </svg>
            </template>
            <p class="text-sm font-medium text-gray-800 dark:text-gray-100" x-text="toast.message"></p>
            <button type="button" @click="remove(toast.id)"
                    class="ms-auto text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>
