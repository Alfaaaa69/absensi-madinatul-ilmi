<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <h2 class="text-lg font-semibold text-zinc-900">Rekap Semua Absensi</h2>

    @include('recap.partials.periode-error')

    @if (! $tahunAjaran)
        <p class="text-sm text-zinc-600">Belum ada tahun ajaran. Hubungi admin.</p>
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
                    class="rounded-md border border-zinc-900 bg-white px-3 py-2 text-sm text-zinc-900"
                >
                    Unduh PDF
                </button>
            </div>
        </div>

        @if ($result === null)
            <p class="text-sm text-zinc-600">
                Pilih periode yang berada di dalam tahun ajaran {{ $tahunAjaran->name }}.
            </p>
        @elseif ($result->classes->isEmpty())
            <p class="text-sm text-zinc-600">Tidak ada kelas pada tahun ajaran ini.</p>
        @elseif (! $result->hasData())
            {{-- Kelas dan siswa ada, tapi belum ada satu pun catatan absensi
                 di periode ini. Menampilkan tabel berisi baris nol akan
                 terbaca seperti semua siswa tidak hadir, jadi kalimat yang
                 sama dengan partial matriks dipakai di sini. --}}
            <p class="text-sm text-zinc-600">@include('recap.partials.kosong')</p>
        @else
            @include('recap.partials.matrix', ['result' => $result])
        @endif
    @endif
</div>