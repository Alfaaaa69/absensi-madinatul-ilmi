<div class="space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-zinc-900">
            {{ $this->salam }}, {{ auth()->user()->name }}
        </h2>
        @if ($academicYear !== null)
            <p class="text-sm text-zinc-600">
                {{ $academicYear->name }} &middot; Semester {{ $academicYear->period->value }}
            </p>
        @else
            <div class="mt-1 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                Tahun ajaran aktif belum diatur. Hubungi admin untuk mengaturnya sebelum mencatat absensi.
            </div>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <flux:card class="p-4">
            <flux:heading size="sm" class="text-zinc-600">Kelas Diawasi</flux:heading>
            <p class="text-2xl font-semibold text-zinc-900">{{ $homeroomCount }}</p>
        </flux:card>

        <flux:card class="p-4">
            <flux:heading size="sm" class="text-zinc-600">Hadir</flux:heading>
            <p class="text-2xl font-semibold text-green-700">{{ $counts['hadir'] }}</p>
        </flux:card>

        <flux:card class="p-4">
            <flux:heading size="sm" class="text-zinc-600">Izin</flux:heading>
            <p class="text-2xl font-semibold text-blue-700">{{ $counts['izin'] }}</p>
        </flux:card>

        <flux:card class="p-4">
            <flux:heading size="sm" class="text-zinc-600">Sakit</flux:heading>
            <p class="text-2xl font-semibold text-yellow-700">{{ $counts['sakit'] }}</p>
        </flux:card>

        <flux:card class="p-4">
            <flux:heading size="sm" class="text-zinc-600">Alpa</flux:heading>
            <p class="text-2xl font-semibold text-red-700">{{ $counts['alpa'] }}</p>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="sm" class="text-zinc-600">Jadwal Saya Hari Ini</flux:heading>

        @if ($schedules->isEmpty())
            <p class="py-6 text-sm text-zinc-500">Tidak ada jadwal hari ini.</p>
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
                            <flux:table.cell class="font-medium text-zinc-900">
                                {{ $jadwal->subject->name }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $jadwal->schoolClass->name }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
