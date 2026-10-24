<div class="space-y-6">
    <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Atur Jeda</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-madrasah-50 px-4 py-3 text-sm text-madrasah-800 dark:text-madrasah-200 dark:bg-madrasah-400/10">{{ session('sukses') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:bg-zinc-900 dark:border-zinc-800">
            <h2 class="font-medium text-zinc-900 dark:text-zinc-100">
                {{ $editingId === null ? 'Tambah Jeda' : 'Ubah Jeda' }}
            </h2>

            <label class="block text-sm">
                <span class="text-zinc-700 dark:text-zinc-300">Hari</span>
                <select wire:model="day_of_week" class="mt-1 w-full rounded border border-zinc-300 dark:border-zinc-700">
                    @foreach ($hari as $item)
                        <option value="{{ $item->value }}">{{ $item->label() }}</option>
                    @endforeach
                </select>
                @error('day_of_week') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-zinc-700 dark:text-zinc-300">Nama Jeda</span>
                <input type="text" wire:model="label" maxlength="50" placeholder="Istirahat"
                    class="mt-1 w-full rounded border border-zinc-300 dark:border-zinc-700" />
                @error('label') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <div class="grid grid-cols-2 gap-2">
                <label class="block text-sm">
                    <span class="text-zinc-700 dark:text-zinc-300">Mulai</span>
                    <input type="time" wire:model="start_time"
                        class="mt-1 w-full rounded border border-zinc-300 dark:border-zinc-700" />
                    @error('start_time') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-zinc-700 dark:text-zinc-300">Selesai</span>
                    <input type="time" wire:model="end_time"
                        class="mt-1 w-full rounded border border-zinc-300 dark:border-zinc-700" />
                    @error('end_time') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="block text-sm">
                <span class="text-zinc-700 dark:text-zinc-300">Setelah jam ke-</span>
                <input type="number" wire:model="after" min="0" max="20"
                    class="mt-1 w-full rounded border border-zinc-300 dark:border-zinc-700" />
                <span class="text-xs text-zinc-500 dark:text-zinc-400">0 = sebelum jam pelajaran pertama.</span>
                @error('after') <span class="block text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <div class="flex gap-2">
                <button type="submit" class="rounded bg-madrasah-600 hover:bg-madrasah-700 px-3 py-2 text-sm text-white">Simpan</button>
                <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
            </div>
        </form>

        <div class="lg:col-span-2 space-y-3">
            <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:bg-zinc-900 dark:border-zinc-800">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-4 py-2">Hari</th>
                            <th class="px-4 py-2">Nama</th>
                            <th class="px-4 py-2">Jam</th>
                            <th class="px-4 py-2">Setelah jam</th>
                            <th class="px-4 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $jeda)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800" wire:key="jeda-{{ $jeda->id }}">
                                <td class="px-4 py-2">{{ $jeda->day_of_week->label() }}</td>
                                <td class="px-4 py-2">{{ $jeda->label }}</td>
                                <td class="px-4 py-2">{{ $jeda->start_time->format('H:i') }}–{{ $jeda->end_time->format('H:i') }}</td>
                                <td class="px-4 py-2">{{ $jeda->after === 0 ? 'Sebelum jam 1' : 'Jam '.$jeda->after }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button wire:click="editForm('{{ $jeda->id }}')" class="text-sm text-madrasah-700 dark:text-madrasah-300">Ubah</button>
                                    <button wire:click="hapus('{{ $jeda->id }}')" wire:confirm="Hapus jeda ini?"
                                        class="ml-2 text-sm text-red-700 dark:text-red-300">Hapus</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">
                                    Belum ada jeda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
