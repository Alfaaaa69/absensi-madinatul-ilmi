<label class="text-sm text-zinc-700">Tahun ajaran
    <select wire:model.live="academicYearId" class="mt-1 block rounded-md border-zinc-300 text-sm">
        @foreach ($daftarTahun as $tahun)
            <option value="{{ $tahun->id }}">{{ $tahun->name }}</option>
        @endforeach
    </select>
</label>

@if ($tampilkanHarian ?? true)
    <label class="text-sm text-zinc-700">Jenis periode
        <select wire:model.live="periodType" class="mt-1 block rounded-md border-zinc-300 text-sm">
            <option value="monthly">Bulanan</option>
            <option value="daily">Harian</option>
        </select>
    </label>
@endif

@if ($periodType === 'monthly')
    <label class="text-sm text-zinc-700">Bulan
        <input type="month" wire:model.live="month" class="mt-1 block rounded-md border-zinc-300 text-sm">
    </label>
@else
    <label class="text-sm text-zinc-700">Tanggal
        <input type="date" wire:model.live="date" class="mt-1 block rounded-md border-zinc-300 text-sm">
    </label>
@endif