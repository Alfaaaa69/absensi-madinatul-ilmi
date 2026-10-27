<div class="space-y-6">
    <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Tahun Ajaran</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-madrasah-50 px-4 py-3 text-sm text-madrasah-800 dark:text-madrasah-200 dark:bg-madrasah-400/10">
            {{ session('sukses') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:bg-zinc-900 dark:border-zinc-800">
            <h2 class="font-medium text-zinc-900 dark:text-zinc-100">
                {{ $editingId === null ? 'Tambah Tahun Ajaran' : 'Ubah Tahun Ajaran' }}
            </h2>

            <label class="block text-sm">
                <span class="text-zinc-700 dark:text-zinc-300">Nama Tahun Ajaran</span>
                <input type="text" wire:model="name" placeholder="2025/2026"
                    class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700" />
                @error('name') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-zinc-700 dark:text-zinc-300">Periode</span>
                <select wire:model="period" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700">
                    @foreach ($periode as $item)
                        <option value="{{ $item->value }}">{{ ucfirst($item->value) }}</option>
                    @endforeach
                </select>
                @error('period') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-zinc-700 dark:text-zinc-300">Mulai</span>
                <input type="date" wire:model="start_date" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700" />
                @error('start_date') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-zinc-700 dark:text-zinc-300">Selesai</span>
                <input type="date" wire:model="end_date" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700" />
                @error('end_date') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                <input type="checkbox" wire:model="is_active" class="rounded border-zinc-300 text-madrasah-600 focus:ring-madrasah-500 dark:border-zinc-700" />
                Jadikan tahun ajaran aktif
            </label>

            <div class="flex gap-2 pt-1">
                <button type="submit" class="rounded-md bg-madrasah-600 hover:bg-madrasah-700 px-4 py-2 text-sm text-white">Simpan</button>
                <button type="button" wire:click="newForm" class="rounded-md border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Reset</button>
            </div>
        </form>

        <div class="lg:col-span-2 overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:bg-zinc-900 dark:border-zinc-800">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-2">Nama</th>
                        <th class="px-4 py-2">Periode</th>
                        <th class="px-4 py-2">Rentang</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tahunAjaran as $tahun)
                        <tr class="border-t border-zinc-100 dark:border-zinc-800">
                            <td class="px-4 py-2">{{ $tahun->name }}</td>
                            <td class="px-4 py-2">{{ ucfirst($tahun->period->value) }}</td>
                            <td class="px-4 py-2">
                                {{ $tahun->start_date->format('d/m/Y') }} - {{ $tahun->end_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-2">
                                @if ($tahun->is_active)
                                    <span class="rounded bg-madrasah-100 px-2 py-1 text-xs text-madrasah-800 dark:text-madrasah-200 dark:bg-madrasah-400/20">Aktif</span>
                                @else
                                    <span class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600 dark:text-zinc-400 dark:bg-zinc-800">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="editForm('{{ $tahun->id }}')" class="text-sm text-madrasah-700 dark:text-madrasah-300">Ubah</button>
                                <button wire:click="setActive('{{ $tahun->id }}')" class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $tahun->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">
                                Belum ada tahun ajaran. Tambahkan satu sebelum membuat kelas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
