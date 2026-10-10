<x-layouts::app.sidebar title="Rekap">
    <flux:main>
        <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
            <nav class="flex gap-2 border-b border-zinc-200 text-sm dark:border-zinc-800">
                @php
                    $linkJadwalSaya = \App\Support\NavLink::guruOnly('Jadwal Saya', 'jadwal.index', 'calendar-days');
                @endphp
                @if ($linkJadwalSaya->visibleTo(auth()->user()->isAdmin()))
                    <a href="{{ route($linkJadwalSaya->routeName) }}" class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ $linkJadwalSaya->label }}</a>
                @endif
                @foreach ($tabRekap as $tab)
                    <span class="border-b-2 border-blue-600 px-3 py-2 font-semibold text-blue-700 dark:text-blue-300">{{ $tab['label'] }}</span>
                @endforeach
            </nav>

            @foreach ($tabRekap as $tab)
                @livewire($tab['component'])
            @endforeach
        </div>
    </flux:main>
</x-layouts::app.sidebar>