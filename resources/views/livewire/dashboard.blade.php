<div class="space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
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
            <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Kelas Diawasi</flux:heading>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $homeroomCount }}</p>
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

    @if (auth()->user()->isAdmin() && $academicYear !== null)
        <div class="grid gap-4 md:grid-cols-2">
            <flux:card>
                <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Absensi 7 Hari Terakhir</flux:heading>

                @if ($last7HasData)
                    <div class="h-64" x-data="{ config: @js($chartLast7Days) }" wire:ignore>
                        <canvas x-init="new Chart($el, config)"></canvas>
                    </div>
                @else
                    <p class="py-6 text-sm text-zinc-500 dark:text-zinc-300">Belum ada data absensi 7 hari terakhir.</p>
                @endif
            </flux:card>

            <flux:card>
                <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Absensi Hari Ini</flux:heading>

                @if ($todayHasData)
                    <div class="h-64" x-data="{ config: @js($chartToday) }" wire:ignore>
                        <canvas x-init="new Chart($el, config)"></canvas>
                    </div>
                @else
                    <p class="py-6 text-sm text-zinc-500 dark:text-zinc-300">Belum ada data absensi hari ini.</p>
                @endif
            </flux:card>
        </div>
    @endif

    @if (! auth()->user()->isAdmin())
        <flux:card>
            <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">Jadwal Saya Hari Ini</flux:heading>

            @if ($schedules->isEmpty())
                <p class="py-6 text-sm text-zinc-500 dark:text-zinc-300">Tidak ada jadwal mengajar hari ini.</p>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Waktu</flux:table.column>
                        <flux:table.column>Mata Pelajaran</flux:table.column>
                        <flux:table.column>Kelas</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($schedules as $jadwal)
                            <flux:table.row :key="$jadwal->id">
                                <flux:table.cell>
                                    {{ $jadwal->start_time->format('H:i') }}
                                    -
                                    {{ $jadwal->end_time->format('H:i') }}
                                </flux:table.cell>
                                <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-200">
                                    {{ $jadwal->subject->name }}
                                </flux:table.cell>
                                <flux:table.cell>{{ $jadwal->schoolClass->name }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif
</div>
