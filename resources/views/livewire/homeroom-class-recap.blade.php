<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Rekap Kelas Saya</h2>

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

            @include('recap.partials.matrix', ['result' => $result])
        @endif
    @endif
</div>