<div class="py-12">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-800 dark:text-zinc-200">Jadwal Mengajar Hari Ini</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</p>
        </div>

        @if ($baris->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:bg-zinc-900 dark:border-zinc-700">
                <p class="text-base font-medium text-zinc-800 dark:text-zinc-200">Tidak ada jadwal mengajar hari ini.</p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Cek lagi besok. Kalau jadwal yang Anda ajar hari ini memang seharusnya ada, hubungi admin.</p>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($baris as $item)
                    <li wire:key="jadwal-{{ $item['jadwal']->id }}"
                        class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:bg-zinc-900 dark:border-zinc-800">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ \App\Support\IndonesianDate::jam($item['jadwal']->start_time) }} - {{ \App\Support\IndonesianDate::jam($item['jadwal']->end_time) }}
                                </p>
                                <p class="mt-1 text-base font-semibold text-zinc-800 dark:text-zinc-200">{{ $item['jadwal']->subject->name }}</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                    Kelas {{ $item['jadwal']->schoolClass->name }} - {{ $item['jumlahSiswa'] }} siswa
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-3">
                                @if ($item['sudahAbsen'])
                                    <span class="rounded-full bg-madrasah-100 px-3 py-1 text-xs font-semibold text-madrasah-800 dark:text-madrasah-200 dark:bg-madrasah-400/20">Sudah absen</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:text-amber-200 dark:bg-amber-400/20">Belum absen</span>
                                @endif
                                <a href="{{ route('absensi.create', $item['jadwal']) }}"
                                   class="rounded-lg bg-madrasah-600 px-4 py-2 text-sm font-semibold text-white hover:bg-madrasah-700">
                                    {{ $item['sudahAbsen'] ? 'Perbarui Absensi' : 'Isi Absensi' }}
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
