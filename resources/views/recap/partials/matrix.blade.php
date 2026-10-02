<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead>
            <tr class="border-b">
                <th class="px-2 py-1 text-left">No</th>
                <th class="px-2 py-1 text-left">NISN</th>
                <th class="px-2 py-1 text-left">Nama Siswa</th>
                <th class="px-2 py-1 text-left">Kelas</th>
                @foreach ($result->subjects as $mapel)
                    <th class="px-2 py-1">{{ $mapel->name }}</th>
                @endforeach
                <th class="px-2 py-1">Hadir</th>
                <th class="px-2 py-1">Izin</th>
                <th class="px-2 py-1">Sakit</th>
                <th class="px-2 py-1">Alpa</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result->rows as $index => $row)
                <tr class="border-b hover:bg-zinc-50">
                    <td class="px-2 py-1">{{ $index + 1 }}</td>
                    <td class="px-2 py-1">{{ $row['student']->nisn }}</td>
                    <td class="px-2 py-1">
                        @if (isset($rowUrl))
                            <button type="button" wire:click="{{ $rowUrl($row['student']) }}" class="text-blue-700 underline">{{ $row['student']->name }}</button>
                        @else
                            {{ $row['student']->name }}
                        @endif
                    </td>
                    <td class="px-2 py-1">{{ $row['class']->name }}</td>
                    @foreach ($result->subjects as $mapel)
                        @php $cell = $row['cells'][$mapel->id] ?? null; @endphp
                        <td class="px-2 py-1">{{ $cell !== null && $cell['total'] > 0 ? $cell['hadir'].'/'.$cell['total'] : '-' }}</td>
                    @endforeach
                    <td class="px-2 py-1">{{ $row['total']['hadir'] }}</td>
                    <td class="px-2 py-1">{{ $row['total']['izin'] }}</td>
                    <td class="px-2 py-1">{{ $row['total']['sakit'] }}</td>
                    <td class="px-2 py-1">{{ $row['total']['alpa'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 4 + $result->subjects->count() + 4 }}" class="px-2 py-3 text-center text-zinc-500">
                        Tidak ada data absensi pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3 text-sm text-zinc-700">
    Ringkasan — Hadir {{ $result->summary['hadir'] }}, Izin {{ $result->summary['izin'] }},
    Sakit {{ $result->summary['sakit'] }}, Alpa {{ $result->summary['alpa'] }}
</div>