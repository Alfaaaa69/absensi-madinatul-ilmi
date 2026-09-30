<div class="py-12">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-800">Jadwal Mengajar Hari Ini</h1>
            <p class="mt-1 text-sm text-zinc-600">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</p>
        </div>

        @if ($baris->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-10 text-center">
                <p class="text-base font-medium text-zinc-800">Tidak ada jadwal mengajar hari ini.</p>
                <p class="mt-1 text-sm text-zinc-500">Cek lagi besok, atau hubungi admin bila jadwal Anda belum terdaftar.</p>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($baris as $item)
                    <li wire:key="jadwal-{{ $item['jadwal']->id }}"
                        class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-2xl font-bold text-zinc-900">
                                    {{ \App\Support\IndonesianDate::jam($item['jadwal']->start_time) }} - {{ \App\Support\IndonesianDate::jam($item['jadwal']->end_time) }}
                                </p>
                                <p class="mt-1 text-base font-semibold text-zinc-800">{{ $item['jadwal']->subject->name }}</p>
                                <p class="text-sm text-zinc-600">
                                    Kelas {{ $item['jadwal']->schoolClass->name }} - {{ $item['jumlahSiswa'] }} siswa
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-3">
                                @if ($item['sudahAbsen'])
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Sudah absen</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Belum absen</span>
                                @endif
                                <a href="{{ route('absensi.create', $item['jadwal']) }}"
                                   class="rounded-lg bg-zinc-800 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700">
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
