<div class="space-y-6">
    <div>
        <h2 class="border-s-4 border-s-sand-500 ps-3 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
            {{ $this->salam }}, {{ auth()->user()->name }}
        </h2>
        @if ($academicYear !== null)
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ $academicYear->name }} &middot; Semester {{ $academicYear->period->value }}
            </p>
        @else
            <div class="mt-1 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                Tahun ajaran aktif belum diatur. Hubungi admin untuk mengaturnya sebelum mencatat absensi.
            </div>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <flux:card class="border-s-4 border-s-madrasah-600 p-4">
            <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">{{ $kartuKelas['label'] }}</flux:heading>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $kartuKelas['nilai'] }}</p>
        </flux:card>

        <flux:card class="border-s-4 border-s-madrasah-600 p-4">
            <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Hadir</flux:heading>
            <p class="text-2xl font-semibold text-madrasah-700 dark:text-madrasah-400">{{ $counts['hadir'] }}</p>
        </flux:card>

        <flux:card class="border-s-4 border-s-madrasah-600 p-4">
            <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Izin</flux:heading>
            <p class="text-2xl font-semibold text-blue-700 dark:text-sky-300">{{ $counts['izin'] }}</p>
        </flux:card>

        <flux:card class="border-s-4 border-s-madrasah-600 p-4">
            <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Sakit</flux:heading>
            <p class="text-2xl font-semibold text-yellow-700 dark:text-amber-300">{{ $counts['sakit'] }}</p>
        </flux:card>

        <flux:card class="border-s-4 border-s-madrasah-600 p-4">
            <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Alfa</flux:heading>
            <p class="text-2xl font-semibold text-red-700 dark:text-rose-300">{{ $counts['alpa'] }}</p>
        </flux:card>
    </div>

    @if ($isAdmin && $academicYear !== null)
        <div class="grid gap-4 md:grid-cols-2">
            <flux:card>
                <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Absensi 7 Hari Terakhir</flux:heading>

                <div class="h-64" x-data="{ config: @js($chartLast7Days) }" wire:ignore>
                    <canvas x-init="new Chart($el, config)"></canvas>
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Absensi Hari Ini</flux:heading>

                <div class="h-64" x-data="{ config: @js($chartToday) }" wire:ignore>
                    <canvas x-init="new Chart($el, config)"></canvas>
                </div>
            </flux:card>
        </div>
    @endif

    @if (! $isAdmin)
        <flux:card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Jadwal Saya Hari Ini</flux:heading>

                @if ($progres['total'] > 0)
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">
                        {{ $progres['sudah'] }}/{{ $progres['total'] }} jadwal sudah diabsen
                    </span>
                @endif
            </div>

            @if ($progres['total'] > 0)
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-full rounded-full bg-madrasah-600"
                        style="width: {{ round($progres['sudah'] / $progres['total'] * 100) }}%"></div>
                </div>
            @endif

            @if ($barisJadwal->isEmpty())
                <p class="py-6 text-sm text-zinc-500 dark:text-zinc-300">Tidak ada jadwal mengajar hari ini.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($barisJadwal as $baris)
                        <li wire:key="jadwal-{{ $baris['jadwal']->id }}"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $baris['jadwal']->start_time->format('H:i') }}
                                    -
                                    {{ $baris['jadwal']->end_time->format('H:i') }}
                                </p>
                                <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $baris['jadwal']->subject->name }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Kelas {{ $baris['jadwal']->schoolClass->name }} - {{ $baris['jumlahSiswa'] }} siswa
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                @if ($baris['sudahAbsen'])
                                    <span class="rounded-full bg-madrasah-100 px-3 py-1 text-xs font-semibold text-madrasah-800 dark:bg-madrasah-400/20 dark:text-madrasah-200">Sudah absen</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-400/20 dark:text-amber-200">Belum absen</span>
                                @endif

                                <flux:button size="sm" variant="primary" :href="route('absensi.create', $baris['jadwal'])" wire:navigate>
                                    {{ $baris['sudahAbsen'] ? 'Perbarui Absensi' : 'Isi Absensi' }}
                                </flux:button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>
    @endif
</div>
