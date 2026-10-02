<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <h2 class="text-lg font-semibold text-zinc-900">Rekap Kelas Saya</h2>

    @if ($periodError)
        <div class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $periodError }}</div>
    @endif

    @if (! $tahunAjaran)
        <p class="text-sm text-zinc-600">Belum ada tahun ajaran. Hubungi admin.</p>
    @else
        <div class="flex flex-wrap items-end gap-3">
            @include('recap.partials.filter-periode')

            @if ($daftarKelas->count() > 1)
                <label class="text-sm text-zinc-700">Pilih kelas
                    <select wire:model.live="classId" class="mt-1 block rounded-md border-zinc-300 text-sm">
                        <option value="">Semua kelas</option>
                        @foreach ($daftarKelas as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        @if ($result === null)
            <p class="text-sm text-zinc-600">Pilih periode yang berada di dalam tahun ajaran {{ $tahunAjaran->name }}.</p>
        @elseif ($daftarKelas->isEmpty())
            <p class="text-sm text-zinc-600">Kamu belum diwalikan kelas. Hubungi admin.</p>
        @else
            @include('recap.partials.matrix', ['result' => $result])
        @endif
    @endif
</div>