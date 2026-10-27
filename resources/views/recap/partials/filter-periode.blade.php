<label class="text-sm text-zinc-700 dark:text-zinc-300">Tahun ajaran
    <select wire:model.live="academicYearId" class="mt-1 block rounded-md border border-zinc-300 text-sm dark:border-zinc-700">
        @foreach ($daftarTahun as $tahun)
            <option value="{{ $tahun->id }}">{{ $tahun->name }}</option>
        @endforeach
    </select>
</label>

@if ($tampilkanHarian ?? true)
    <label class="text-sm text-zinc-700 dark:text-zinc-300">Jenis periode
        <select wire:model.live="periodType" class="mt-1 block rounded-md border border-zinc-300 text-sm dark:border-zinc-700">
            <option value="monthly">Bulanan</option>
            <option value="daily">Harian</option>
        </select>
    </label>
@endif

@if ($periodType === 'monthly')
    <label class="text-sm text-zinc-700 dark:text-zinc-300">Bulan
        <input type="month" wire:model.live="month" class="mt-1 block rounded-md border border-zinc-300 text-sm dark:border-zinc-700">
    </label>
@else
    <label class="text-sm text-zinc-700 dark:text-zinc-300">Tanggal
        <input type="date" wire:model.live="date" class="mt-1 block rounded-md border border-zinc-300 text-sm dark:border-zinc-700">
    </label>
@endif