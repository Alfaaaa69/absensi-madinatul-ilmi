<div class="space-y-6">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Siswa</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:text-green-200 dark:bg-green-400/10">{{ session('sukses') }}</div>
    @endif

    <form wire:submit="save" class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-5 dark:bg-zinc-900 dark:border-zinc-800">
        <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-300">NISN</span>
            <input type="text" wire:model="nisn" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700" />
            @error('nisn') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-300">Nama</span>
            <input type="text" wire:model="name" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700" />
            @error('name') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-300">Jenis Kelamin</span>
            <select wire:model="gender" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700">
                @foreach ($jenisKelamin as $item)
                    <option value="{{ $item->value }}">{{ $item->value === 'L' ? 'Laki-laki' : 'Perempuan' }}</option>
                @endforeach
            </select>
            @error('gender') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-300">Kelas</span>
            <select wire:model="class_id" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700">
                <option value="">Pilih kelas</option>
                @foreach ($daftarKelas as $kelas)
                    <option value="{{ $kelas->id }}">{{ $kelas->name }} ({{ $kelas->academicYear->name }})</option>
                @endforeach
            </select>
            @error('class_id') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
        </label>

        <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">
                {{ $editingId === null ? 'Tambah' : 'Perbarui' }}
            </button>
            <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
        </div>
    </form>

    <div class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-4 dark:bg-zinc-900 dark:border-zinc-800">
        <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-300">Cari NISN atau Nama</span>
            <input type="search" wire:model.live.debounce.300ms="search"
                class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700" />
        </label>

        <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-300">Filter Kelas</span>
            <select wire:model.live="filterClassId" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700">
                <option value="">Semua kelas</option>
                @foreach ($daftarKelas as $kelas)
                    <option value="{{ $kelas->id }}">{{ $kelas->name }} ({{ $kelas->academicYear->name }})</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-300">Filter Jenis Kelamin</span>
            <select wire:model.live="filterGender" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700">
                <option value="">Semua</option>
                @foreach ($jenisKelamin as $item)
                    <option value="{{ $item->value }}">{{ $item->value === 'L' ? 'Laki-laki' : 'Perempuan' }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-end gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" wire:click="toggleArsip" />
            Tampilkan yang diarsipkan
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:bg-zinc-900 dark:border-zinc-800">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:text-gray-400 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-2">NISN</th>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">L/P</th>
                    <th class="px-4 py-2">Kelas</th>
                    <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($siswa as $item)
                    <tr class="border-t border-gray-100 dark:border-zinc-800">
                        <td class="px-4 py-2">{{ $item->nisn }}</td>
                        <td class="px-4 py-2">
                            {{ $item->name }}
                            @if ($item->trashed())
                                <span class="ml-2 rounded bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:text-gray-400 dark:bg-zinc-800">Diarsipkan</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $item->gender->value }}</td>
                        <td class="px-4 py-2">
                            {{ $item->schoolClass->name }} ({{ $item->schoolClass->academicYear->name }})
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="editForm('{{ $item->id }}')" class="text-sm text-blue-700 dark:text-blue-300">Ubah</button>
                            @if ($item->trashed())
                                <button wire:click="pulihkan('{{ $item->id }}')" class="ml-2 text-sm text-green-700 dark:text-green-300">Pulihkan</button>
                            @else
                                <button wire:click="hapus('{{ $item->id }}')" class="ml-2 text-sm text-red-700 dark:text-red-300">Hapus</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            Tidak ada siswa yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
        <span>{{ $totalSiswa }} siswa</span>
        <span class="flex gap-2">
            <button wire:click="gotoPage({{ $page - 1 }})" @disabled($page <= 1)
                class="rounded border px-3 py-1 disabled:opacity-40">Sebelumnya</button>
            <span>Halaman {{ $page }}</span>
            <button wire:click="gotoPage({{ $page + 1 }})" @disabled($page * 15 >= $totalSiswa)
                class="rounded border px-3 py-1 disabled:opacity-40">Berikutnya</button>
        </span>
    </div>
</div>
