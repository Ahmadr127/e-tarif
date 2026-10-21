@props(['parameters'])

<div x-data="{ open: false }">
    <button type="button"
            @click="open = true; $nextTick(() => $refs.variableSearch?.focus())"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/30 rounded-md bg-white hover:bg-sp-primary/5 transition-colors"
            title="Bantuan kode parameter">
        <i class="bi bi-question-circle"></i>
        <span class="hidden sm:inline">Bantuan Kode</span>
    </button>

    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="variable-help-title"
         @keydown.escape.window="open = false">
        <div class="absolute inset-0 bg-gray-900/50" @click="open = false"></div>

        <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div>
                    <h3 id="variable-help-title" class="text-base font-bold text-sp-navy">Bantuan Kode Parameter</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Klik Salin untuk menyalin referensi {kode} parameter ke formula.</p>
                </div>
                <button type="button"
                        @click="open = false"
                        class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                        title="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="border-b border-gray-100 p-4">
                <input type="search"
                       id="variable-search"
                       x-ref="variableSearch"
                       placeholder="Cari kode atau nama parameter..."
                       class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
                @forelse($parameters as $parameter)
                    <div class="variable-item px-4 py-3" data-search="{{ mb_strtolower($parameter->formulaKey().' '.$parameter->name) }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-mono text-xs font-semibold text-sp-navy break-all">{{ '{'.$parameter->formulaKey().'}' }}</div>
                                <div class="text-xs text-gray-700">{{ $parameter->name }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-1 text-[10px] text-gray-500">
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5">{{ $parameter->value_type }}</span>
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5">{{ $parameter->scope }}</span>
                                    @if($parameter->unit)<span class="rounded bg-gray-100 px-1.5 py-0.5">{{ $parameter->unit }}</span>@endif
                                </div>
                            </div>
                            <button type="button" data-copy-variable="{{ '{'.$parameter->formulaKey().'}' }}"
                                    class="copy-variable flex-shrink-0 inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5"
                                    title="Salin referensi kode parameter">
                                <i class="bi bi-clipboard"></i> Salin
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-gray-500">Belum ada parameter aktif.</div>
                @endforelse
            </div>

            <div class="border-t border-amber-200 bg-amber-50 px-5 py-3 text-xs text-amber-800">
                Kode parameter adalah variabel global. Formula yang mereferensikan parameter yang belum lengkap
                akan berstatus belum lengkap, bukan dianggap nol.
            </div>
        </div>
    </div>
</div>
