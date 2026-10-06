<div class="space-y-6">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Mata Pelajaran</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:text-green-200 dark:bg-green-400/10">{{ session('sukses') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:bg-zinc-900 dark:border-zinc-800">
            <h2 class="font-medium text-gray-900 dark:text-gray-100">
                {{ $editingId === null ? 'Tambah Mata Pelajaran' : 'Ubah Mata Pelajaran' }}
            </h2>

            <label class="block text-sm">
                <span class="text-gray-700 dark:text-gray-300">Kode</span>
                <input type="text" wire:model="code" maxlength="10" placeholder="MTK"
                    class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700" />
                @error('code') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700 dark:text-gray-300">Nama Mata Pelajaran</span>
                <input type="text" wire:model="name"
                    class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700" />
                @error('name') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <div class="flex gap-2">
                <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">Simpan</button>
                <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
            </div>
        </form>

        <div class="lg:col-span-2 space-y-3">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:click="toggleArsip" />
                Tampilkan yang diarsipkan
            </label>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:bg-zinc-900 dark:border-zinc-800">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:text-gray-400 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-4 py-2">Kode</th>
                            <th class="px-4 py-2">Nama</th>
                            <th class="px-4 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $mapel)
                            <tr class="border-t border-gray-100 dark:border-zinc-800">
                                <td class="px-4 py-2">{{ $mapel->code }}</td>
                                <td class="px-4 py-2">
                                    {{ $mapel->name }}
                                    @if ($mapel->trashed())
                                        <span class="ml-2 rounded bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:text-gray-400 dark:bg-zinc-800">Diarsipkan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <button wire:click="editForm('{{ $mapel->id }}')" class="text-sm text-blue-700 dark:text-blue-300">Ubah</button>
                                    @if ($mapel->trashed())
                                        <button wire:click="pulihkan('{{ $mapel->id }}')" class="ml-2 text-sm text-green-700 dark:text-green-300">Pulihkan</button>
                                    @else
                                        <button wire:click="hapus('{{ $mapel->id }}')" class="ml-2 text-sm text-red-700 dark:text-red-300">Hapus</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    Belum ada mata pelajaran.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
