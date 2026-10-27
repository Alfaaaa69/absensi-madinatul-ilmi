{{-- `rows` berisi satu baris per siswa, bukan per catatan absensi, jadi
     kelas yang punya siswa tapi belum ada absensi di periode ini tetap punya
     baris penuh angka nol. Tanpa kalimat di sini, tabel nol itu terbaca
     seperti "semua siswa tidak hadir". Baris ringkasan tetap dirender: angka
     nol yang sahih lebih berguna daripada tabel yang hilang. --}}
@if (! $result->hasData())
    <p class="mb-2 text-sm text-zinc-600 dark:text-zinc-400">@include('recap.partials.kosong')</p>
@endif

@include('recap.partials.cutoff')

<div class="overflow-x-auto rounded-lg border border-madrasah-200 dark:border-zinc-700">
    <table class="min-w-full text-sm">
        <thead>
            <tr class="bg-madrasah-700 text-white dark:bg-madrasah-800">
                <th class="px-2 py-2 text-left font-semibold">No</th>
                <th class="px-2 py-2 text-left font-semibold">NISN</th>
                <th class="px-2 py-2 text-left font-semibold">Nama Siswa</th>
                <th class="px-2 py-2 text-left font-semibold">Kelas</th>
                @foreach ($result->subjects as $mapel)
                    <th class="px-2 py-2 font-semibold">{{ $mapel->name }}</th>
                @endforeach
                @if ($result->subjects->isEmpty())
                    <th class="px-2 py-2 font-semibold">Absensi</th>
                @endif
                <th class="px-2 py-2 font-semibold">Hadir</th>
                <th class="px-2 py-2 font-semibold">Izin</th>
                <th class="px-2 py-2 font-semibold">Sakit</th>
                <th class="px-2 py-2 font-semibold">Alfa</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result->rows as $index => $row)
                <tr class="border-b border-zinc-100 odd:bg-white even:bg-madrasah-50/50 hover:bg-madrasah-50 dark:border-zinc-800 dark:odd:bg-zinc-900 dark:even:bg-zinc-800/60 dark:hover:bg-zinc-800">
                    <td class="px-2 py-1">{{ $index + 1 }}</td>
                    <td class="px-2 py-1">{{ $row['student']->nisn }}</td>
                    <td class="px-2 py-1">
                        @if (isset($rowUrl))
                            <button type="button" wire:click="{{ $rowUrl($row['student']) }}" class="text-madrasah-700 underline dark:text-madrasah-300">{{ $row['student']->name }}</button>
                        @else
                            {{ $row['student']->name }}
                        @endif
                    </td>
                    <td class="px-2 py-1">{{ $row['class']->name }}</td>
                    @foreach ($result->subjects as $mapel)
                        @php $cell = $row['cells'][$mapel->id] ?? null; @endphp
                        <td class="px-2 py-1 text-center">{{ $cell !== null && $cell['total'] > 0 ? $cell['hadir'].'/'.$cell['total'] : '-' }}</td>
                    @endforeach
                    @if ($result->subjects->isEmpty())
                        <td class="px-2 py-1 text-center">
                            <span class="inline-block min-w-12 rounded-full bg-madrasah-100 px-2 py-0.5 text-xs font-semibold text-madrasah-800 dark:bg-madrasah-400/20 dark:text-madrasah-200">
                                {{ $row['total']['total'] }}
                            </span>
                        </td>
                    @endif
                    <td class="px-2 py-1 text-center"><span class="inline-block min-w-7 rounded-full bg-madrasah-100 px-2 py-0.5 text-xs font-semibold text-madrasah-800 dark:bg-madrasah-400/20 dark:text-madrasah-200">{{ $row['total']['hadir'] }}</span></td>
                    <td class="px-2 py-1 text-center"><span class="inline-block min-w-7 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-400/20 dark:text-blue-200">{{ $row['total']['izin'] }}</span></td>
                    <td class="px-2 py-1 text-center"><span class="inline-block min-w-7 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-400/20 dark:text-amber-200">{{ $row['total']['sakit'] }}</span></td>
                    <td class="px-2 py-1 text-center"><span class="inline-block min-w-7 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-400/20 dark:text-red-200">{{ $row['total']['alpa'] }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 4 + $result->subjects->count() + 4 + ($result->subjects->isEmpty() ? 1 : 0) }}" class="px-2 py-3 text-center text-zinc-500 dark:text-zinc-400">
                        @include('recap.partials.kosong')
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('recap.partials.summary', ['result' => $result])