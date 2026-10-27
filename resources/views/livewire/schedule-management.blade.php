<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Jadwal Pelajaran</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Pilih kelas untuk melihat jadwal seminggu.</p>
        </div>

        <button type="button" wire:click="newForm" class="rounded bg-madrasah-600 px-3 py-2 text-sm font-medium text-white hover:bg-madrasah-700">
            Tambah Jadwal
        </button>
    </div>

    @if (session('sukses'))
        <div class="rounded-md bg-madrasah-50 px-4 py-3 text-sm text-madrasah-800 dark:text-madrasah-200 dark:bg-madrasah-400/10">{{ session('sukses') }}</div>
    @endif

    <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:grid-cols-2 lg:grid-cols-4">
        <label class="block text-sm">
            <span class="text-zinc-700 dark:text-zinc-300">Tahun Ajaran</span>
            <select wire:model.live="academic_year_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700">
                @foreach ($tahunAjaran as $tahun)
                    <option value="{{ $tahun->id }}">{{ $tahun->name }} ({{ ucfirst($tahun->period->value) }})</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm">
            <span class="text-zinc-700 dark:text-zinc-300">Kelas</span>
            <select wire:model.live="selectedClassId" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700">
                @forelse ($daftarKelas as $kelas)
                    <option value="{{ $kelas->id }}">{{ $kelas->name }}{{ $kelas->trashed() ? ' (diarsipkan)' : '' }}</option>
                @empty
                    <option value="">Belum ada kelas</option>
                @endforelse
            </select>
        </label>

        <label class="block text-sm sm:col-span-2">
            <span class="text-zinc-700 dark:text-zinc-300">Cari</span>
            <input type="text" wire:model.live.debounce.300ms="searchJadwal" placeholder="Cari kelas, mapel, atau guru" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700" />
        </label>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
            <input type="checkbox" wire:click="toggleArsip" @checked($showArchived) />
            Tampilkan yang diarsipkan
        </label>

        <p class="text-xs text-zinc-500 dark:text-zinc-400">Klik kartu jadwal untuk mengubah.</p>

        <a href="{{ route('master.jeda') }}" wire:navigate
            class="rounded border border-zinc-300 px-3 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
            Atur Jeda
        </a>
    </div>

    @if ($kelasAktif)
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <div>
                    <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Kelas {{ $kelasAktif->name }}</h2>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                        Wali kelas: {{ $kelasAktif->homeroomTeacher?->name ?? 'Belum ditentukan' }}
                    </p>
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 sm:hidden">Geser tabel ke samping untuk melihat semua hari.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 w-24 border-b border-zinc-200 bg-zinc-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                                Jam
                            </th>
                            @foreach ($hari as $item)
                                <th @class([
                                    'border-b border-zinc-200 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide dark:border-zinc-800',
                                    'bg-madrasah-50 text-madrasah-700 dark:bg-madrasah-400/10 dark:text-madrasah-200' => now()->dayOfWeekIso === $item->value,
                                    'bg-zinc-50 text-zinc-500 dark:bg-zinc-800/50 dark:text-zinc-400' => now()->dayOfWeekIso !== $item->value,
                                ])>
                                    {{ $item->label() }}
                                    @if (now()->dayOfWeekIso === $item->value)
                                        <span class="block text-[10px] font-normal normal-case tracking-normal">Hari ini</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grid as $slot)
                            @if ($slot['type'] === 'break')
                                <tr>
                                    <th class="sticky left-0 z-10 border-y-2 border-amber-300 bg-amber-50 px-3 py-2 text-center text-[11px] font-semibold text-amber-800 dark:border-amber-700 dark:bg-amber-400/10 dark:text-amber-200">
                                        Jeda
                                    </th>
                                    @foreach ($hari as $item)
                                        <td data-jeda-hari="{{ $item->value }}" class="border-y-2 border-amber-300 bg-amber-50 px-3 py-2 text-center align-middle dark:border-amber-700 dark:bg-amber-400/10">
                                            @forelse ($slot['jeda']->filter(fn ($jeda) => $jeda['day'] === $item) as $jeda)
                                                <div @class([
                                                    'flex flex-wrap items-center justify-center gap-x-2 text-xs text-amber-700 dark:text-amber-300',
                                                    'mt-1' => ! $loop->first,
                                                ])>
                                                    <span class="font-semibold text-amber-900 dark:text-amber-100">{{ $jeda['label'] }}</span>
                                                    <span>·</span>
                                                    <span>{{ str_replace(':', '.', $jeda['start']) }}–{{ str_replace(':', '.', $jeda['end']) }}</span>
                                                </div>
                                            @empty
                                                &nbsp;
                                            @endforelse
                                        </td>
                                    @endforeach
                                </tr>
                            @else
                                <tr>
                                    <th class="sticky left-0 z-10 border-b border-zinc-100 bg-white px-3 py-2 text-left align-top font-medium text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                                        <span class="block">Jam {{ $slot['nomor'] }}</span>
                                    </th>

                                @foreach ($hari as $item)
                                    @php($jadwalList = $slot['hari']->get($item->value, collect()))

                                    <td class="border-b border-zinc-100 p-1.5 align-top dark:border-zinc-800">
                                        @forelse ($jadwalList as $jadwal)
                                            <div @class([
                                                'rounded-lg border p-2 transition',
                                                'border-zinc-200 bg-white hover:border-madrasah-300 hover:bg-madrasah-50/40 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-madrasah-600' => ! $jadwal->trashed(),
                                                'border-dashed border-zinc-300 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/40' => $jadwal->trashed(),
                                                'mt-1' => ! $loop->first,
                                            ])>
                                                <button type="button" wire:click="editForm('{{ $jadwal->id }}')" class="block w-full text-left">
                                                    <span class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $jadwal->subject->name }}</span>
                                                    <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">{{ $jadwal->teacher->name }}</span>
                                                    <span class="mt-1 block text-[11px] text-zinc-400 dark:text-zinc-500">
                                                        {{ $jadwal->start_time->format('H:i') }}–{{ $jadwal->end_time->format('H:i') }}
                                                    </span>
                                                    @if ($jadwal->trashed())
                                                        <span class="mt-1 inline-flex rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">Diarsipkan</span>
                                                    @endif
                                                </button>

                                                <div class="mt-2 flex items-center gap-2 border-t border-zinc-100 pt-1.5 text-[11px] dark:border-zinc-800">
                                                    <button type="button" wire:click="editForm('{{ $jadwal->id }}')" class="text-madrasah-700 hover:underline dark:text-madrasah-300">Ubah</button>

                                                    @if ($jadwal->trashed())
                                                        <button type="button" wire:click="pulihkan('{{ $jadwal->id }}')" class="text-green-700 hover:underline dark:text-green-300">Pulihkan</button>
                                                    @else
                                                        <button type="button" wire:click="askDelete('{{ $jadwal->id }}')" class="text-red-700 hover:underline dark:text-red-300">
                                                            {{ $jadwal->memiliki_riwayat ? 'Arsipkan' : 'Hapus' }}
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <div class="flex min-h-20 items-center justify-center rounded-lg border border-dashed border-zinc-200 text-xs text-zinc-300 dark:border-zinc-800 dark:text-zinc-600">
                                                —
                                            </div>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    Belum ada jadwal untuk kelas ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="rounded-lg border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            Belum ada kelas pada tahun ajaran ini.
        </div>
    @endif

    <div @class([
        'fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center' => $showForm,
        'hidden' => ! $showForm,
    ]) wire:click.self="closeForm" aria-hidden="{{ $showForm ? 'false' : 'true' }}">
        <div class="w-full max-w-lg rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $editingId === null ? 'Tambah Jadwal' : 'Ubah Jadwal' }}
                    </h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Tahun ajaran {{ $tahunAjaran->firstWhere('id', $academic_year_id)?->name ?? '-' }}
                    </p>
                </div>
                <button type="button" wire:click="closeForm" class="text-xl leading-none text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-200" aria-label="Tutup">&times;</button>
            </div>

            <form wire:submit="save" class="mt-4 space-y-3">
                <label class="block text-sm">
                    <span class="text-zinc-700 dark:text-zinc-300">Kelas</span>
                    <select wire:model="class_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700">
                        <option value="">Pilih kelas</option>
                        @foreach ($daftarKelas as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->name }}{{ $kelas->trashed() ? ' (diarsipkan)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('class_id') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-zinc-700 dark:text-zinc-300">Mata Pelajaran</span>
                    <select wire:model="subject_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700">
                        <option value="">Pilih mata pelajaran</option>
                        @foreach ($daftarMapel as $mapel)
                            <option value="{{ $mapel->id }}">{{ $mapel->name }}</option>
                        @endforeach
                    </select>
                    @error('subject_id') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-zinc-700 dark:text-zinc-300">Guru Pengajar</span>
                    <select wire:model="teacher_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700">
                        <option value="">Pilih guru</option>
                        @foreach ($daftarGuru as $guru)
                            <option value="{{ $guru->id }}">{{ $guru->name }}</option>
                        @endforeach
                    </select>
                    @error('teacher_id') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-zinc-700 dark:text-zinc-300">Hari</span>
                    <select wire:model="day_of_week" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700">
                        @foreach ($hari as $item)
                            <option value="{{ $item->value }}">{{ $item->label() }}</option>
                        @endforeach
                    </select>
                    @error('day_of_week') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <label class="block text-sm">
                        <span class="text-zinc-700 dark:text-zinc-300">Mulai</span>
                        <input type="time" wire:model.live="start_time" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700" />
                        @error('start_time') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                    </label>

                    <label class="block text-sm">
                        <span class="text-zinc-700 dark:text-zinc-300">Selesai</span>
                        <input type="time" wire:model="end_time" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700" />
                        @error('end_time') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" wire:click="closeForm" class="rounded border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700">Batal</button>
                    <button type="submit" class="rounded bg-madrasah-600 px-3 py-2 text-sm text-white hover:bg-madrasah-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @if ($pendingDeleteId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg border border-zinc-200 bg-white p-6 dark:bg-zinc-900 dark:border-zinc-800">
                <h2 class="font-medium text-zinc-900 dark:text-zinc-100">
                    {{ $pendingDeletePermanent ? 'Hapus Permanen' : 'Arsipkan' }} — {{ $pendingDeleteName }}
                </h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $pendingDeleteMessage }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete"
                        class="rounded border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700">Batal</button>
                    <button type="button" wire:click="confirmDelete" class="rounded bg-red-700 px-3 py-2 text-sm text-white">
                        {{ $pendingDeletePermanent ? 'Hapus Permanen' : 'Arsipkan' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
