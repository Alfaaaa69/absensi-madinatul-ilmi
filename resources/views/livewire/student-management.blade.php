<div class="space-y-6">
    <!-- Header & Action -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Data Siswa</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Kelola informasi data siswa dan penempatan kelas.</p>
        </div>
        <div>
            <button
                type="button"
                wire:click="openCreateModal"
                class="inline-flex items-center gap-2 rounded-lg bg-madrasah-700 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-madrasah-800 transition-colors focus:ring-2 focus:ring-madrasah-500"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Siswa
            </button>
        </div>
    </div>

    @if (session('sukses'))
        <div class="rounded-lg border border-madrasah-200 bg-madrasah-50 px-4 py-3 text-sm text-madrasah-800 dark:border-zinc-800 dark:bg-madrasah-400/10 dark:text-madrasah-200">
            {{ session('sukses') }}
        </div>
    @endif

    <!-- Mini Metric Cards -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Siswa Aktif</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $totalAktif }}</span>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">siswa</span>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Komposisi Gender</span>
            <div class="mt-1 flex items-center gap-2">
                <span class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 dark:text-blue-300">
                    <span class="size-2 rounded-full bg-blue-500"></span> {{ $totalLaki }} L
                </span>
                <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                <span class="inline-flex items-center gap-1 text-xs font-semibold text-rose-700 dark:text-rose-300">
                    <span class="size-2 rounded-full bg-rose-500"></span> {{ $totalPerempuan }} P
                </span>
            </div>
        </div>
        <div class="col-span-2 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs sm:col-span-1 dark:border-zinc-800 dark:bg-zinc-900">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Hasil Filter</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-madrasah-700 dark:text-madrasah-300">{{ $totalSiswa }}</span>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">ditampilkan</span>
            </div>
        </div>
    </div>

    <!-- Filter & Pencarian -->
    <div class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs md:grid-cols-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div>
            <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300">Cari NISN atau Nama</label>
            <div class="relative mt-1">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Ketik NISN atau nama..."
                    class="w-full rounded-lg border border-zinc-300 py-2 pl-3 pr-8 text-sm shadow-xs focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                />
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300">Filter Kelas</label>
            <select
                wire:model.live="filterClassId"
                class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-xs focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
            >
                <option value="">Semua kelas</option>
                @foreach ($daftarKelas as $kelas)
                    <option value="{{ $kelas->id }}">{{ $kelas->name }} ({{ $kelas->academicYear->name }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300">Filter Jenis Kelamin</label>
            <select
                wire:model.live="filterGender"
                class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-xs focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
            >
                <option value="">Semua</option>
                @foreach ($jenisKelamin as $item)
                    <option value="{{ $item->value }}">{{ $item->value === 'L' ? 'Laki-laki' : 'Perempuan' }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-xs font-medium text-zinc-700 dark:text-zinc-300 cursor-pointer pb-2">
                <input
                    type="checkbox"
                    wire:click="toggleArsip"
                    class="rounded border-zinc-300 text-madrasah-600 focus:ring-madrasah-500 dark:border-zinc-700"
                />
                Tampilkan yang diarsipkan
            </label>
        </div>
    </div>

    <!-- TAMPILAN DESKTOP: TABEL -->
    <div class="hidden md:block overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 border-b border-zinc-200 text-left text-xs font-semibold text-zinc-600 uppercase tracking-wider dark:bg-zinc-800/60 dark:border-zinc-800 dark:text-zinc-300">
                <tr>
                    <th class="px-4 py-3">NISN</th>
                    <th class="px-4 py-3">Nama Siswa</th>
                    <th class="px-4 py-3 text-center">L/P</th>
                    <th class="px-4 py-3">Kelas</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($siswa as $item)
                    <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 transition-colors">
                        <td class="px-4 py-3 font-mono text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $item->nisn }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ mb_substr($item->name, 0, 1) }}
                                </span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $item->name }}
                                </span>
                                @if ($item->trashed())
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-400/20 dark:text-amber-300">
                                        Diarsipkan
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($item->gender->value === 'L')
                                <span class="inline-block rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-400/15 dark:text-blue-300">L</span>
                            @else
                                <span class="inline-block rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-400/15 dark:text-rose-300">P</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-md bg-madrasah-50 px-2 py-1 text-xs font-semibold text-madrasah-800 dark:bg-madrasah-400/15 dark:text-madrasah-200">
                                {{ $item->schoolClass->name }}
                            </span>
                            <span class="text-xs text-zinc-400 dark:text-zinc-500 ml-1">({{ $item->schoolClass->academicYear->name }})</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="editForm('{{ $item->id }}')"
                                    class="rounded-md border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                >
                                    Ubah
                                </button>
                                @if ($item->trashed())
                                    <button
                                        type="button"
                                        wire:click="pulihkan('{{ $item->id }}')"
                                        class="rounded-md border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-100 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-900/50"
                                    >
                                        Pulihkan
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="askDelete('{{ $item->id }}')"
                                        class="rounded-md border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-800/60 dark:text-red-400 dark:hover:bg-red-900/20"
                                    >
                                        {{ $item->memiliki_riwayat ? 'Arsipkan' : 'Hapus' }}
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                            Tidak ada siswa yang cocok dengan filter pencarian.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- TAMPILAN MOBILE: KARTU SISWA (CARD LIST) -->
    <div class="md:hidden space-y-3">
        @forelse ($siswa as $item)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-madrasah-100 text-sm font-bold text-madrasah-800 dark:bg-madrasah-400/20 dark:text-madrasah-300">
                            {{ mb_substr($item->name, 0, 1) }}
                        </span>
                        <div>
                            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</h3>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span class="font-mono">NISN: {{ $item->nisn }}</span>
                                <span>&bull;</span>
                                @if ($item->gender->value === 'L')
                                    <span class="text-blue-600 dark:text-blue-400 font-medium">Laki-laki</span>
                                @else
                                    <span class="text-rose-600 dark:text-rose-400 font-medium">Perempuan</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <span class="inline-flex shrink-0 items-center rounded-md bg-madrasah-50 px-2 py-1 text-xs font-bold text-madrasah-800 dark:bg-madrasah-400/20 dark:text-madrasah-200">
                        {{ $item->schoolClass->name }}
                    </span>
                </div>

                @if ($item->trashed())
                    <div class="mt-2">
                        <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-400/20 dark:text-amber-300">
                            Status: Diarsipkan
                        </span>
                    </div>
                @endif

                <div class="mt-3.5 flex items-center justify-end gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                    <button
                        type="button"
                        wire:click="editForm('{{ $item->id }}')"
                        class="min-h-[36px] rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    >
                        Ubah
                    </button>
                    @if ($item->trashed())
                        <button
                            type="button"
                            wire:click="pulihkan('{{ $item->id }}')"
                            class="min-h-[36px] rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700"
                        >
                            Pulihkan
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="askDelete('{{ $item->id }}')"
                            class="min-h-[36px] rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-800/60 dark:text-red-400 dark:hover:bg-red-900/30"
                        >
                            {{ $item->memiliki_riwayat ? 'Arsipkan' : 'Hapus' }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                Tidak ada siswa yang cocok dengan filter.
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between text-xs sm:text-sm text-zinc-600 dark:text-zinc-400">
        <span>Menampilkan {{ count($siswa) }} dari {{ $totalSiswa }} siswa</span>
        <div class="flex items-center gap-1.5 sm:gap-2">
            <button
                type="button"
                wire:click="gotoPage({{ $page - 1 }})"
                @disabled($page <= 1)
                class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs disabled:opacity-40 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                Sebelumnya
            </button>
            <span class="px-2 font-medium">Hal {{ $page }}</span>
            <button
                type="button"
                wire:click="gotoPage({{ $page + 1 }})"
                @disabled($page * 15 >= $totalSiswa)
                class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs disabled:opacity-40 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                Berikutnya
            </button>
        </div>
    </div>

    <!-- MODAL POPUP FORM TAMBAH / UBAH SISWA -->
    <div
        x-data="{ open: @entangle('showFormModal') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <!-- Backdrop -->
        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 backdrop-blur-xs"
            wire:click="closeModal"
        ></div>

        <!-- Dialog Box -->
        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                    {{ $editingId === null ? 'Tambah Siswa Baru' : 'Ubah Data Siswa' }}
                </h2>
                <button
                    type="button"
                    wire:click="closeModal"
                    class="rounded-md text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">NISN</label>
                    <input
                        type="text"
                        wire:model="nisn"
                        placeholder="Contoh: 3200123456"
                        class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-xs focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    />
                    @error('nisn') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Nama Siswa</label>
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="Nama lengkap siswa"
                        class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-xs focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    />
                    @error('name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Jenis Kelamin</label>
                        <select
                            wire:model="gender"
                            class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-xs focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            @foreach ($jenisKelamin as $item)
                                <option value="{{ $item->value }}">{{ $item->value === 'L' ? 'Laki-laki' : 'Perempuan' }}</option>
                            @endforeach
                        </select>
                        @error('gender') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Kelas</label>
                        <select
                            wire:model="class_id"
                            class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-xs focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="">Pilih kelas</option>
                            @foreach ($daftarKelas as $kelas)
                                <option value="{{ $kelas->id }}">{{ $kelas->name }} ({{ $kelas->academicYear->name }})</option>
                            @endforeach
                        </select>
                        @error('class_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-madrasah-700 px-5 py-2 text-sm font-semibold text-white shadow-xs hover:bg-madrasah-800 focus:ring-2 focus:ring-madrasah-500 transition-colors"
                    >
                        {{ $editingId === null ? 'Simpan Siswa' : 'Perbarui' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL KONFIRMASI HAPUS/ARSIP -->
    @if ($pendingDeleteId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
            <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                    {{ $pendingDeletePermanent ? 'Hapus Permanen' : 'Arsipkan' }} &mdash; {{ $pendingDeleteName }}
                </h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">{{ $pendingDeleteMessage }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelDelete"
                        class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="confirmDelete"
                        class="rounded-lg bg-red-600 hover:bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-xs"
                    >
                        {{ $pendingDeletePermanent ? 'Hapus Permanen' : 'Arsipkan' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
