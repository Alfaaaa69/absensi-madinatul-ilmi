<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Rekap Semua Absensi</h2>

    @include('recap.partials.periode-error')

    @if (! $tahunAjaran)
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Belum ada tahun ajaran. Hubungi admin.</p>
    @else
        <div class="flex flex-wrap items-end justify-between gap-3">
            @include('recap.partials.filter-periode')

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    wire:click="exportExcel"
                    class="rounded-md bg-zinc-900 px-3 py-2 text-sm text-white"
                >
                    Unduh Excel
                </button>

                <button
                    type="button"
                    wire:click="exportPdf"
                    class="rounded-md border border-zinc-900 bg-white px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 dark:bg-zinc-900"
                >
                    Unduh PDF
                </button>
            </div>
        </div>

        @if ($result === null)
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Pilih periode yang berada di dalam tahun ajaran {{ $tahunAjaran->name }}.
            </p>
        @elseif ($result->classes->isEmpty())
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Tidak ada kelas pada tahun ajaran ini.</p>
        @elseif ($result->rows->isEmpty())
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Kelas ada, tetapi belum ada siswa terdaftar.</p>
        @elseif ($kelasTerpilih)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <button
                        type="button"
                        wire:click="kembali"
                        class="text-sm font-medium text-madrasah-700 hover:underline dark:text-madrasah-300"
                    >
                        ← Daftar kelas
                    </button>

                    <h3 class="mt-1 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                        Kelas {{ $kelasTerpilih->name }}
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Wali kelas: {{ $kelasTerpilih->homeroomTeacher?->name ?? 'Belum ditentukan' }}
                    </p>
                </div>
            </div>

            @if ($detail->rows->isEmpty())
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Belum ada siswa terdaftar di kelas ini.</p>
            @else
                @include('recap.partials.matrix', ['result' => $detail])
            @endif
        @else
            @include('recap.partials.cutoff', ['result' => $result])

            @if (! $result->hasData())
                <p class="mb-2 text-sm text-zinc-600 dark:text-zinc-400">@include('recap.partials.kosong')</p>
            @endif

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($result->classes as $kelas)
                    <button
                        type="button"
                        wire:click="pilihKelas('{{ $kelas->id }}')"
                        class="rounded-xl border border-zinc-200 bg-white p-4 text-left transition hover:border-madrasah-300 hover:bg-madrasah-50/40 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-madrasah-600 dark:hover:bg-zinc-800"
                    >
                        <span class="block text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $kelas->name }}
                        </span>
                        <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                            Wali: {{ $kelas->homeroomTeacher?->name ?? 'Belum ditentukan' }}
                        </span>
                        <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $jumlahSiswa[$kelas->id] ?? 0 }} siswa
                        </span>
                    </button>
                @endforeach
            </div>

            @include('recap.partials.summary', ['result' => $result])
        @endif
    @endif
</div>