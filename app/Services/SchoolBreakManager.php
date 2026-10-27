<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\SchoolBreak;
use Illuminate\Validation\ValidationException;

final class SchoolBreakManager
{
    /**
     * @param  array{day_of_week: string, label: string, start_time: string, end_time: string, after: int|string}  $data
     */
    public function save(?SchoolBreak $break, array $data): SchoolBreak
    {
        $mulai = $this->waktu($data['start_time']);
        $selesai = $this->waktu($data['end_time']);

        $jedaBentrok = SchoolBreak::query()
            ->where('day_of_week', (int) $data['day_of_week'])
            ->when($break !== null, fn ($query) => $query->where('id', '!=', $break->id))
            ->get()
            ->first(fn (SchoolBreak $jeda) => $this->tumpangTindih(
                $jeda->start_time->format('H:i:s'),
                $jeda->end_time->format('H:i:s'),
                $mulai,
                $selesai,
            ));

        if ($jedaBentrok !== null) {
            throw ValidationException::withMessages([
                'start_time' => "Jam {$data['start_time']}–{$data['end_time']} sudah dipakai jeda {$jedaBentrok->label}.",
            ]);
        }

        $jadwalBentrok = Schedule::query()
            ->with(['schoolClass', 'subject'])
            ->where('day_of_week', (int) $data['day_of_week'])
            ->get()
            ->first(fn (Schedule $jadwal) => $this->tumpangTindih(
                $jadwal->start_time->format('H:i:s'),
                $jadwal->end_time->format('H:i:s'),
                $mulai,
                $selesai,
            ));

        if ($jadwalBentrok !== null) {
            throw ValidationException::withMessages([
                'start_time' => sprintf(
                    'Jam ini dipakai jadwal %s %s (%s–%s).',
                    $jadwalBentrok->schoolClass?->name,
                    $jadwalBentrok->subject?->name,
                    $jadwalBentrok->start_time->format('H:i'),
                    $jadwalBentrok->end_time->format('H:i'),
                ),
            ]);
        }

        $jeda = $break ?? new SchoolBreak;

        $jeda->fill([
            'day_of_week' => (int) $data['day_of_week'],
            'label' => $data['label'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'after' => (int) $data['after'],
        ]);
        $jeda->save();

        return $jeda;
    }

    public function hapus(SchoolBreak $break): void
    {
        $break->delete();
    }

    private function waktu(string $waktu): string
    {
        return strlen($waktu) === 5 ? $waktu.':00' : $waktu;
    }

    private function tumpangTindih(string $mulaiA, string $selesaiA, string $mulaiB, string $selesaiB): bool
    {
        return $this->detik($mulaiA) < $this->detik($selesaiB)
            && $this->detik($mulaiB) < $this->detik($selesaiA);
    }

    private function detik(string $waktu): int
    {
        [$jam, $menit, $detik] = array_pad(explode(':', $waktu), 3, '0');

        return ((int) $jam) * 3600 + ((int) $menit) * 60 + (int) $detik;
    }
}
