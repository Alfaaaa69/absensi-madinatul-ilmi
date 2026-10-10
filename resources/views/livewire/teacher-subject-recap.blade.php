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
            @include('recap.partials.matrix', ['result' => $result])
        @endif
    @endif
</div>