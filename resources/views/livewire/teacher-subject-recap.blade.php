<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <h2 class="text-lg font-semibold text-zinc-900">Rekap Mata Pelajaran Saya</h2>

    @include('recap.partials.periode-error')

    @if (! $tahunAjaran)
        <p class="text-sm text-zinc-600">Belum ada tahun ajaran. Hubungi admin.</p>
    @else
        <div class="flex flex-wrap items-end gap-3">
            @include('recap.partials.filter-periode', ['tampilkanHarian' => false])
        </div>

        @if ($result === null)
            <p class="text-sm text-zinc-600">Pilih bulan yang berada di dalam tahun ajaran {{ $tahunAjaran->name }}.</p>
        @elseif ($result->classes->isEmpty())
            <p class="text-sm text-zinc-600">Belum ada jadwal mengajar pada tahun ajaran ini.</p>
        @else
            @include('recap.partials.matrix', ['result' => $result])
        @endif
    @endif
</div>