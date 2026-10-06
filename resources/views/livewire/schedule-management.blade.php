<div class="space-y-6">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Jadwal Pelajaran</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:text-green-200 dark:bg-green-400/10">{{ session('sukses') }}</div>
    @endif

    @error('start_time')
        <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:text-red-200 dark:bg-red-400/10">{{ $message }}</div>
    @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:bg-zinc-900 dark:border-zinc-800">
            <h2 class="font-medium text-gray-900 dark:text-gray-100">
                {{ $editingId === null ? 'Tambah Jadwal' : 'Ubah Jadwal' }}
            </h2>

            <label class="block text-sm">
                <span class="text-gray-700 dark:text-gray-300">Tahun Ajaran</span>
                <select wire:model.live="academic_year_id" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700">
                    @foreach ($tahunAjaran as $tahun)
                        <option value="{{ $tahun->id }}">{{ $tahun->name }} ({{ ucfirst($tahun->period->value) }})</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm">
                <span class="text-gray-700 dark:text-gray-300">Kelas</span>
                <select wire:model="class_id" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700">
                    <option value="">Pilih kelas</option>
                    @foreach ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}">{{ $kelas->name }}</option>
                    @endforeach
                </select>
                @error('class_id') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700 dark:text-gray-300">Mata Pelajaran</span>
                <select wire:model="subject_id" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700">
                    <option value="">Pilih mata pelajaran</option>
                    @foreach ($daftarMapel as $mapel)
                        <option value="{{ $mapel->id }}">{{ $mapel->name }}</option>
                    @endforeach
                </select>
                @error('subject_id') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700 dark:text-gray-300">Guru Pengajar</span>
                <select wire:model="teacher_id" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700">
                    <option value="">Pilih guru</option>
                    @foreach ($daftarGuru as $guru)
                        <option value="{{ $guru->id }}">{{ $guru->name }}</option>
                    @endforeach
                </select>
                @error('teacher_id') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700 dark:text-gray-300">Hari</span>
                <select wire:model="day_of_week" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700">
                    @foreach ($hari as $item)
                        <option value="{{ $item->value }}">{{ $item->label() }}</option>
                    @endforeach
                </select>
                @error('day_of_week') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
            </label>

            <div class="grid grid-cols-2 gap-2">
                <label class="block text-sm">
                    <span class="text-gray-700 dark:text-gray-300">Mulai</span>
                    <input type="time" wire:model="start_time" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700" />
                    @error('start_time') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-700 dark:text-gray-300">Selesai</span>
                    <input type="time" wire:model="end_time" class="mt-1 w-full rounded border border-gray-300 dark:border-zinc-700" />
                    @error('end_time') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                </label>
            </div>

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
                            <th class="px-4 py-2">Hari</th>
                            <th class="px-4 py-2">Jam</th>
                            <th class="px-4 py-2">Kelas</th>
                            <th class="px-4 py-2">Mapel</th>
                            <th class="px-4 py-2">Guru</th>
                            <th class="px-4 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $jadwal)
                            <tr class="border-t border-gray-100 dark:border-zinc-800">
                                <td class="px-4 py-2">{{ $jadwal->day_of_week->label() }}</td>
                                <td class="px-4 py-2">
                                    {{ $jadwal->start_time->format('H:i') }} - {{ $jadwal->end_time->format('H:i') }}
                                </td>
                                <td class="px-4 py-2">{{ $jadwal->schoolClass->name }}</td>
                                <td class="px-4 py-2">{{ $jadwal->subject->name }}</td>
                                <td class="px-4 py-2">{{ $jadwal->teacher->name }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button wire:click="editForm('{{ $jadwal->id }}')" class="text-sm text-blue-700 dark:text-blue-300">Ubah</button>
                                    @if ($jadwal->trashed())
                                        <button wire:click="pulihkan('{{ $jadwal->id }}')" class="ml-2 text-sm text-green-700 dark:text-green-300">Pulihkan</button>
                                    @else
                                        <button wire:click="hapus('{{ $jadwal->id }}')" class="ml-2 text-sm text-red-700 dark:text-red-300">Hapus</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    Belum ada jadwal pada tahun ajaran ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
