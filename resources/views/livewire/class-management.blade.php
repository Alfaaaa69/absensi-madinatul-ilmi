<div class="space-y-6">
    <h1 class="text-lg font-semibold text-gray-900">Kelas</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="font-medium text-gray-900">
                {{ $editingId === null ? 'Tambah Kelas' : 'Ubah Kelas' }}
            </h2>

            <label class="block text-sm">
                <span class="text-gray-700">Tahun Ajaran</span>
                <select wire:model="academic_year_id" class="mt-1 w-full rounded border-gray-300">
                    @foreach ($tahunAjaran as $tahun)
                        <option value="{{ $tahun->id }}">{{ $tahun->name }} ({{ ucfirst($tahun->period->value) }})</option>
                    @endforeach
                </select>
                @error('academic_year_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Nama Kelas</span>
                <input type="text" wire:model="name" placeholder="VII-A"
                    class="mt-1 w-full rounded border-gray-300" />
                @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Tingkat</span>
                <select wire:model="grade_level" class="mt-1 w-full rounded border-gray-300">
                    @foreach ([7, 8, 9] as $tingkat)
                        <option value="{{ $tingkat }}">Kelas {{ $tingkat }}</option>
                    @endforeach
                </select>
                @error('grade_level') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Wali Kelas</span>
                <select wire:model="homeroom_teacher_id" class="mt-1 w-full rounded border-gray-300">
                    <option value="">Belum ditentukan</option>
                    @foreach ($waliTersedia as $wali)
                        <option value="{{ $wali->id }}">{{ $wali->name }}</option>
                    @endforeach
                </select>
                @error('homeroom_teacher_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="flex gap-2">
                <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">Simpan</button>
                <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
            </div>
        </form>

        <div class="lg:col-span-2 space-y-3">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:click="toggleArsip" />
                Tampilkan yang diarsipkan
            </label>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Kelas</th>
                            <th class="px-4 py-2">Tahun Ajaran</th>
                            <th class="px-4 py-2">Wali Kelas</th>
                            <th class="px-4 py-2">Siswa</th>
                            <th class="px-4 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $kelas)
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-2">
                                    {{ $kelas->name }}
                                    @if ($kelas->trashed())
                                        <span class="ml-2 rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Diarsipkan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $kelas->academicYear?->name }}</td>
                                <td class="px-4 py-2">
                                    {{ $kelas->homeroomTeacher?->name ?? 'Belum ditentukan' }}
                                </td>
                                <td class="px-4 py-2">{{ $kelas->students_count }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button wire:click="editForm('{{ $kelas->id }}')" class="text-sm text-blue-700">Ubah</button>
                                    @if ($kelas->trashed())
                                        <button wire:click="pulihkan('{{ $kelas->id }}')" class="ml-2 text-sm text-green-700">Pulihkan</button>
                                    @else
                                        <button wire:click="arsipkan('{{ $kelas->id }}')" class="ml-2 text-sm text-red-700">Arsipkan</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                    Belum ada kelas pada tahun ajaran ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
