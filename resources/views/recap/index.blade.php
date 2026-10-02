<x-layouts::app.sidebar title="Rekap">
    <flux:main>
        <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
            <nav class="flex gap-2 border-b border-zinc-200 text-sm">
                <a href="{{ route('jadwal.index') }}" class="px-3 py-2 text-zinc-600">Jadwal Saya</a>
                @foreach ($tabRekap as $tab)
                    <span class="border-b-2 border-blue-600 px-3 py-2 font-semibold text-blue-700">{{ $tab['label'] }}</span>
                @endforeach
            </nav>

            @foreach ($tabRekap as $tab)
                @livewire($tab['component'])
            @endforeach
        </div>
    </flux:main>
</x-layouts::app.sidebar>