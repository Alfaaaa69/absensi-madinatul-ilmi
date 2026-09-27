<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kehadiran Siswa</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
        h1 { font-size: 14px; text-align: center; margin: 0 0 2px; }
        .meta { font-size: 9px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 3px 4px; text-align: center; }
        th { background: #f0f0f0; }
        td.nama, td.kelas { text-align: left; }
        .ringkasan { margin-top: 12px; font-size: 9px; }
    </style>
</head>
<body>
    <h1>LAPORAN KEHADIRAN SISWA</h1>
    <div class="meta">
        Tahun Ajaran: {{ $tahun?->name ?? '-' }} &nbsp;|&nbsp;
        Periode: {{ $result->period->label() }} &nbsp;|&nbsp;
        Kelas: {{ $result->classes->pluck('name')->implode(', ') ?: '-' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NISN</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                @foreach ($result->subjects as $mapel)
                    <th>{{ $mapel->name }}</th>
                @endforeach
                <th>Hadir</th>
                <th>Izin</th>
                <th>Sakit</th>
                <th>Alpa</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result->rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['student']->nisn }}</td>
                    <td class="nama">{{ $row['student']->name }}</td>
                    <td class="kelas">{{ $row['class']->name }}</td>
                    @foreach ($result->subjects as $mapel)
                        @php $cell = $row['cells'][$mapel->id] ?? null; @endphp
                        <td>
                            @if ($cell !== null && $cell['total'] > 0)
                                {{ $cell['hadir'] }}/{{ $cell['total'] }}
                            @else
                                -
                            @endif
                        </td>
                    @endforeach
                    <td>{{ $row['total']['hadir'] }}</td>
                    <td>{{ $row['total']['izin'] }}</td>
                    <td>{{ $row['total']['sakit'] }}</td>
                    <td>{{ $row['total']['alpa'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 4 + $result->subjects->count() + 4 }}">Tidak ada data pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="ringkasan">
        <strong>Ringkasan:</strong>
        Hadir {{ $result->summary['hadir'] }},
        Izin {{ $result->summary['izin'] }},
        Sakit {{ $result->summary['sakit'] }},
        Alpa {{ $result->summary['alpa'] }}
    </div>
</body>
</html>
