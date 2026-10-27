<div class="space-y-6">
    <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Impor Siswa dari Excel</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-madrasah-50 px-4 py-3 text-sm text-madrasah-800 dark:text-madrasah-200 dark:bg-madrasah-400/10">{{ session('sukses') }}</div>
    @endif

    <form wire:submit="preview" class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 md:grid-cols-4 dark:bg-zinc-900 dark:border-zinc-800">
        <label class="block text-sm">
            <span class="text-zinc-700 dark:text-zinc-300">Tahun Ajaran</span>
            <select wire:model="academic_year_id" class="mt-1 w-full rounded border border-zinc-300 dark:border-zinc-700">
                @foreach ($tahunAjaran as $tahun)
                    <option value="{{ $tahun->id }}">{{ $tahun->name }} ({{ ucfirst($tahun->period->value) }})</option>
                @endforeach
            </select>
            @error('academic_year_id') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm md:col-span-2">
            <span class="text-zinc-700 dark:text-zinc-300">File Excel (.xlsx, maksimal 5 MB)</span>
            <input type="file" wire:model="file" accept=".xlsx"
                class="mt-1 w-full rounded border border-zinc-300 dark:border-zinc-700" />
            @error('file') <span class="text-xs text-red-600 dark:text-red-300">{{ $message }}</span> @enderror
        </label>

        <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-madrasah-600 hover:bg-madrasah-700 px-3 py-2 text-sm text-white">Periksa</button>
            <button type="button" wire:click="resetForm" class="rounded border px-3 py-2 text-sm">Reset</button>
        </div>
    </form>

    <p class="text-xs text-zinc-500 dark:text-zinc-400">
        Baris 1 harus berisi judul kolom: NISN, Nama, JK, Kelas. Kolom "Kelas" diisi nama kelas
        apa adanya, misalnya VII-A, dan kelas harus ada pada tahun ajaran yang dipilih di atas.
    </p>

    @if ($pratinjau !== [])
        <div class="rounded-lg border border-zinc-200 bg-white dark:bg-zinc-900 dark:border-zinc-800">
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 text-sm dark:border-zinc-800">
                <span class="font-medium">{{ $namaFile }}</span>
                <span>
                    <span class="text-green-700 dark:text-green-300">{{ $jumlahValid }} akan disimpan</span>
                    <span class="ml-2 text-red-700 dark:text-red-300">{{ $jumlahGagal }} ditolak</span>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-4 py-2">Baris</th>
                            <th class="px-4 py-2">NISN</th>
                            <th class="px-4 py-2">Nama</th>
                            <th class="px-4 py-2">L/P</th>
                            <th class="px-4 py-2">Kelas</th>
                            <th class="px-4 py-2">Hasil</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pratinjau as $baris)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800 {{ $baris['valid'] ? '' : 'bg-red-50 dark:bg-red-400/10' }}">
                                <td class="px-4 py-2">{{ $baris['row'] }}</td>
                                <td class="px-4 py-2">{{ $baris['nisn'] }}</td>
                                <td class="px-4 py-2">{{ $baris['nama'] }}</td>
                                <td class="px-4 py-2">{{ $baris['gender'] }}</td>
                                <td class="px-4 py-2">{{ $baris['kelas'] }}</td>
                                <td class="px-4 py-2">
                                    @if ($baris['valid'])
                                        <span class="text-green-700 dark:text-green-300">Akan disimpan</span>
                                    @else
                                        <ul class="list-disc pl-4 text-red-700 dark:text-red-300">
                                            @foreach ($baris['errors'] as $pesan)
                                                <li>{{ $pesan }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-3 border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <button wire:click="confirm" @disabled($jumlahGagal > 0 || $jumlahValid === 0)
                    class="rounded bg-madrasah-600 hover:bg-madrasah-700 px-3 py-2 text-sm text-white disabled:cursor-not-allowed disabled:opacity-40">
                    Simpan {{ $jumlahValid }} siswa
                </button>

                @if ($jumlahGagal > 0)
                    <span class="text-sm text-red-700 dark:text-red-300">
                        Perbaiki {{ $jumlahGagal }} baris yang ditolak, lalu unggah ulang. Tidak ada yang disimpan sebagian.
                    </span>
                @endif
            </div>
        </div>
    @endif
</div>
