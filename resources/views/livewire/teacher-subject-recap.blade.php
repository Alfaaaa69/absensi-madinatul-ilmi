<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Rekap Mata Pelajaran Saya</h2>

    @include('recap.partials.periode-error')

    @if (! $tahunAjaran)
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Belum ada tahun ajaran. Hubungi admin.</p>
    @else
        <div class="flex flex-wrap items-end gap-3">
            @include('recap.partials.filter-periode', ['tampilkanHarian' => false])
        </div>

        @if ($result === null)
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Pilih bulan yang berada di dalam tahun ajaran {{ $tahunAjaran->name }}.</p>
        @elseif ($result->classes->isEmpty())
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Belum ada jadwal mengajar pada tahun ajaran ini.</p>
        @elseif ($result->rows->isEmpty())
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Kelas ada, tetapi belum ada siswa terdaftar.</p>
        @else
            @include('recap.partials.cutoff')

            @if (! $result->hasData())
                <p class="text-sm text-zinc-600 dark:text-zinc-400">@include('recap.partials.kosong')</p>
            @endif

            @php
                $perKelas = $result->rows->groupBy(fn ($row) => $row['class']->id);
            @endphp

            <div class="space-y-3">
                @foreach ($perKelas as $kelasId => $barisKelas)
                    @php
                        $kelas = $barisKelas->first()['class'];
                        $jadwalKelas = $jadwalPerKelas->get($kelas->id, collect());
                        $detailJadwal = $jadwalKelas->groupBy('subject.name')->map(function ($items, $mapelNama) {
                            $slots = $items->map(fn ($j) => "{$j->day_of_week->label()} ({$j->start_time->format('H:i')} - {$j->end_time->format('H:i')})")->join(', ');
                            return "{$mapelNama} · {$slots}";
                        })->join(' • ');

                        $mapelKelas = $result->subjects->filter(
                            fn ($mapel) => $barisKelas->contains(
                                fn ($row) => isset($row['cells'][$mapel->id]) && $row['cells'][$mapel->id]['total'] > 0
                            )
                        );
                        $totalKelas = [
                            'hadir' => $barisKelas->sum(fn ($r) => $r['total']['hadir']),
                            'izin' => $barisKelas->sum(fn ($r) => $r['total']['izin']),
                            'sakit' => $barisKelas->sum(fn ($r) => $r['total']['sakit']),
                            'alpa' => $barisKelas->sum(fn ($r) => $r['total']['alpa']),
                        ];
                    @endphp

                    <flux:card x-data="{ open: false }" class="transition">
                        {{-- Header kartu yang bisa diklik untuk buka/tutup --}}
                        <button type="button"
                                @click="open = !open"
                                class="flex w-full flex-wrap items-center justify-between gap-3 text-left">
                            <div>
                                <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">Kelas {{ $kelas->name }}</flux:heading>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $detailJadwal ?: ($mapelKelas->isNotEmpty() ? $mapelKelas->pluck('name')->join(', ') : $result->subjects->pluck('name')->join(', ')) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-madrasah-100 px-3 py-1 text-xs font-semibold text-madrasah-800 dark:bg-madrasah-400/20 dark:text-madrasah-200">Hadir {{ $totalKelas['hadir'] }}</span>
                                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-400/20 dark:text-blue-200">Izin {{ $totalKelas['izin'] }}</span>
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-400/20 dark:text-amber-200">Sakit {{ $totalKelas['sakit'] }}</span>
                                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800 dark:bg-red-400/20 dark:text-red-200">Alfa {{ $totalKelas['alpa'] }}</span>

                                <span class="inline-flex items-center gap-1 rounded-md border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    <span x-text="open ? 'Tutup' : 'Lihat Detail'">Lihat Detail</span>
                                    <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </span>
                            </div>
                        </button>

                        {{-- Tabel data absen, hanya muncul saat kartu dibuka --}}
                        <div x-show="open" x-cloak class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                            <th class="px-2 py-2 text-left text-zinc-600 dark:text-zinc-400">No</th>
                                            <th class="px-2 py-2 text-left text-zinc-600 dark:text-zinc-400">NISN</th>
                                            <th class="px-2 py-2 text-left text-zinc-600 dark:text-zinc-400">Nama Siswa</th>
                                            <th class="px-2 py-2 text-center text-zinc-600 dark:text-zinc-400">Hadir</th>
                                            <th class="px-2 py-2 text-center text-zinc-600 dark:text-zinc-400">Izin</th>
                                            <th class="px-2 py-2 text-center text-zinc-600 dark:text-zinc-400">Sakit</th>
                                            <th class="px-2 py-2 text-center text-zinc-600 dark:text-zinc-400">Alfa</th>
                                            <th class="px-2 py-2 text-center text-zinc-600 dark:text-zinc-400">% Kehadiran</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($barisKelas->values() as $index => $row)
                                            @php
                                                $total = $row['total']['total'];
                                                $persen = $total > 0 ? round($row['total']['hadir'] / $total * 100) : null;
                                                $rendah = $persen !== null && $persen < 75;
                                            @endphp

                                            <tr class="{{ $rendah ? 'bg-red-50 dark:bg-red-900/20' : '' }} border-b border-zinc-100 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                                                <td class="px-2 py-2">{{ $index + 1 }}</td>
                                                <td class="px-2 py-2">{{ $row['student']->nisn }}</td>
                                                <td class="px-2 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $row['student']->name }}</td>
                                                <td class="px-2 py-2 text-center">{{ $row['total']['hadir'] }}</td>
                                                <td class="px-2 py-2 text-center">{{ $row['total']['izin'] }}</td>
                                                <td class="px-2 py-2 text-center">{{ $row['total']['sakit'] }}</td>
                                                <td class="px-2 py-2 text-center">{{ $row['total']['alpa'] }}</td>
                                                <td class="px-2 py-2 text-center font-semibold {{ $rendah ? 'text-red-700 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                                    {{ $persen !== null ? $persen.'%' : '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    @endif
</div>
