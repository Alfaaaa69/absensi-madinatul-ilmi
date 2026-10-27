<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Rekap Kelas Saya</h2>
        @if ($daftarKelas->isNotEmpty())
            <span class="rounded-full bg-madrasah-100 px-3 py-0.5 text-xs font-semibold text-madrasah-800 dark:bg-madrasah-400/20 dark:text-madrasah-200">
                Wali Kelas: {{ $daftarKelas->pluck('name')->join(', ') }}
            </span>
        @endif
    </div>

    @include('recap.partials.periode-error')

    @if (! $tahunAjaran)
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Belum ada tahun ajaran. Hubungi admin.</p>
    @else
        <div class="flex flex-wrap items-end gap-3">
            @include('recap.partials.filter-periode')

            @if ($daftarKelas->count() > 1)
                <label class="text-sm text-zinc-700 dark:text-zinc-300">Pilih kelas
                    <select wire:model.live="classId" class="mt-1 block rounded-md border border-zinc-300 text-sm dark:border-zinc-700">
                        <option value="">Semua kelas</option>
                        @foreach ($daftarKelas as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        @if ($result === null)
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Pilih periode yang berada di dalam tahun ajaran {{ $tahunAjaran->name }}.</p>
        @elseif ($daftarKelas->isEmpty())
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Kamu belum diwalikan kelas. Hubungi admin.</p>
        @elseif ($result->rows->isEmpty())
            {{-- Kelas ada tapi siswanya belum ada. Menunggu absensi tidak akan
                 membantu di sini, jadi kalimatnya harus berbeda dari kondisi
                 "belum ada data absensi". --}}
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Tidak ada siswa terdaftar di kelas ini.</p>
        @else
            @php $rowUrl = fn ($siswa) => "lihatSiswa('{$siswa->id}')"; @endphp

            @if ($studentId !== null)
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Riwayat Absensi — {{ $siswaDipilih?->name ?? 'Siswa' }}</h3>
                        <button type="button" wire:click="tutupRiwayat" class="rounded-md border border-zinc-300 bg-white px-3 py-1 text-sm dark:bg-zinc-900 dark:border-zinc-700">Tutup</button>
                    </div>

                    @if ($riwayat->isEmpty())
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Riwayat siswa tidak tersedia.</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-2 py-1 text-left">Tanggal</th>
                                    <th class="px-2 py-1 text-left">Mata Pelajaran</th>
                                    <th class="px-2 py-1 text-left">Status</th>
                                    <th class="px-2 py-1 text-left">Alasan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($riwayat as $baris)
                                    <tr class="border-b">
                                        <td class="px-2 py-1">{{ \Illuminate\Support\Carbon::parse($baris['date'])->translatedFormat('d F Y') }}</td>
                                        <td class="px-2 py-1">{{ $baris['mapel'] }}</td>
                                        <td class="px-2 py-1">{{ $this->statusLabel($baris['status']) }}</td>
                                        <td class="px-2 py-1">{{ $baris['note'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endif

            {{-- Ringkasan badge cepat --}}
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full bg-madrasah-100 px-3 py-1 text-xs font-semibold text-madrasah-800 dark:bg-madrasah-400/20 dark:text-madrasah-200">Hadir {{ $result->summary['hadir'] }}</span>
                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-400/20 dark:text-blue-200">Izin {{ $result->summary['izin'] }}</span>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-400/20 dark:text-amber-200">Sakit {{ $result->summary['sakit'] }}</span>
                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800 dark:bg-red-400/20 dark:text-red-200">Alfa {{ $result->summary['alpa'] }}</span>
            </div>

            @include('recap.partials.cutoff')

            @if (! $result->hasData())
                <p class="mb-2 text-sm text-zinc-600 dark:text-zinc-400">@include('recap.partials.kosong')</p>
            @endif

            {{-- Tabel matriks tanpa kolom mapel, hanya ringkasan absensi per siswa --}}
            <div class="overflow-x-auto rounded-lg border border-madrasah-200 dark:border-zinc-700">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-madrasah-700 text-white dark:bg-madrasah-800">
                            <th class="px-2 py-2 text-left font-semibold">No</th>
                            <th class="px-2 py-2 text-left font-semibold">NISN</th>
                            <th class="px-2 py-2 text-left font-semibold">Nama Siswa</th>
                            <th class="px-2 py-2 text-left font-semibold">Kelas</th>
                            <th class="px-2 py-2 text-center font-semibold">Hadir</th>
                            <th class="px-2 py-2 text-center font-semibold">Izin</th>
                            <th class="px-2 py-2 text-center font-semibold">Sakit</th>
                            <th class="px-2 py-2 text-center font-semibold">Alfa</th>
                            <th class="px-2 py-2 text-center font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($result->rows as $index => $row)
                            @php $total = $row['total']['total']; @endphp
                            <tr class="{{ $total > 0 ? 'hover:bg-madrasah-50 dark:hover:bg-zinc-800' : '' }} border-b border-zinc-100 dark:border-zinc-800">
                                <td class="px-2 py-2">{{ $index + 1 }}</td>
                                <td class="px-2 py-2">{{ $row['student']->nisn }}</td>
                                <td class="px-2 py-2 font-medium">
                                    <button type="button" wire:click="{{ $rowUrl($row['student']) }}" class="text-madrasah-700 underline hover:text-madrasah-800 dark:text-madrasah-300">
                                        {{ $row['student']->name }}
                                    </button>
                                </td>
                                <td class="px-2 py-2">{{ $row['class']->name }}</td>
                                <td class="px-2 py-2 text-center"><span class="inline-block min-w-7 rounded-full bg-madrasah-100 px-2 py-0.5 text-xs font-semibold text-madrasah-800 dark:bg-madrasah-400/20 dark:text-madrasah-200">{{ $row['total']['hadir'] }}</span></td>
                                <td class="px-2 py-2 text-center"><span class="inline-block min-w-7 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-400/20 dark:text-blue-200">{{ $row['total']['izin'] }}</span></td>
                                <td class="px-2 py-2 text-center"><span class="inline-block min-w-7 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-400/20 dark:text-amber-200">{{ $row['total']['sakit'] }}</span></td>
                                <td class="px-2 py-2 text-center"><span class="inline-block min-w-7 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-400/20 dark:text-red-200">{{ $row['total']['alpa'] }}</span></td>
                                <td class="px-2 py-2 text-center"><span class="inline-block min-w-8 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200">{{ $total }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-2 py-4 text-center text-zinc-500 dark:text-zinc-400">
                                    @include('recap.partials.kosong')
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 text-sm text-zinc-700 dark:text-zinc-300">
                Ringkasan — Hadir {{ $result->summary['hadir'] }}, Izin {{ $result->summary['izin'] }},
                Sakit {{ $result->summary['sakit'] }},
                Alfa {{ $result->summary['alpa'] }}
            </div>
        @endif
    @endif
</div>
