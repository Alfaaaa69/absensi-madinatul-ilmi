<x-layouts::app.sidebar title="Rekap">
    <flux:main>
        <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8"
             x-data="{ activeTab: '{{ $tabRekap[0]['component'] ?? '' }}' }">
            <nav class="flex gap-2 border-b border-zinc-200 text-sm dark:border-zinc-800">
                @php
                    $linkJadwalSaya = \App\Support\NavLink::guruOnly('Jadwal Saya', 'jadwal.index', 'calendar-days');
                @endphp
                @if ($linkJadwalSaya->visibleTo(auth()->user()->isAdmin()))
                    <a href="{{ route($linkJadwalSaya->routeName) }}" class="px-3 py-2 text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200">{{ $linkJadwalSaya->label }}</a>
                @endif
                @foreach ($tabRekap as $tab)
                    <button type="button"
                            @click="activeTab = '{{ $tab['component'] }}'"
                            :class="activeTab === '{{ $tab['component'] }}'
                                ? 'border-b-2 border-madrasah-600 font-semibold text-madrasah-700 dark:text-madrasah-300'
                                : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200'"
                            class="px-3 py-2 transition">
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </nav>

            @foreach ($tabRekap as $tab)
                <div x-show="activeTab === '{{ $tab['component'] }}'" x-cloak>
                    @livewire($tab['component'])
                </div>
            @endforeach
        </div>
    </flux:main>
</x-layouts::app.sidebar>
