<div class="space-y-6">
    <h1 class="text-lg font-semibold text-gray-900">Tahun Ajaran</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('sukses') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="font-medium text-gray-900">
                {{ $editingId === null ? 'Tambah Tahun Ajaran' : 'Ubah Tahun Ajaran' }}
            </h2>

            <label class="block text-sm">
                <span class="text-gray-700">Nama Tahun Ajaran</span>
                <input type="text" wire:model="name" placeholder="2025/2026"
                    class="mt-1 w-full rounded border-gray-300" />
                @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Periode</span>
                <select wire:model="period" class="mt-1 w-full rounded border-gray-300">
                    @foreach ($periode as $item)
                        <option value="{{ $item->value }}">{{ ucfirst($item->value) }}</option>
                    @endforeach
                </select>
                @error('period') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Mulai</span>
                <input type="date" wire:model="start_date" class="mt-1 w-full rounded border-gray-300" />
                @error('start_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Selesai</span>
                <input type="date" wire:model="end_date" class="mt-1 w-full rounded border-gray-300" />
                @error('end_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="is_active" />
                Jadikan tahun ajaran aktif
            </label>

            <div class="flex gap-2">
                <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">Simpan</button>
                <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
            </div>
        </form>

        <div class="lg:col-span-2 overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
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
                        <tr class="border-t border-gray-100">
                            <td class="px-4 py-2">{{ $tahun->name }}</td>
                            <td class="px-4 py-2">{{ ucfirst($tahun->period->value) }}</td>
                            <td class="px-4 py-2">
                                {{ $tahun->start_date->format('d/m/Y') }} - {{ $tahun->end_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-2">
                                @if ($tahun->is_active)
                                    <span class="rounded bg-green-100 px-2 py-1 text-xs text-green-800">Aktif</span>
                                @else
                                    <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="editForm('{{ $tahun->id }}')" class="text-sm text-blue-700">Ubah</button>
                                <button wire:click="setActive('{{ $tahun->id }}')" class="ml-2 text-sm text-gray-700">
                                    {{ $tahun->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                Belum ada tahun ajaran. Tambahkan satu sebelum membuat kelas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
