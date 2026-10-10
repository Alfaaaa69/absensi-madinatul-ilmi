<?php

namespace App\Attendance\Queries;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\User;
use App\Policies\SchedulePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Jadwal milik satu pengguna untuk satu tanggal.
 *
 * Filter `teacher_id` mempersempit kandidat supaya query ini tidak menarik
 * seluruh jadwal sekolah di setiap permintaan. Setelah itu setiap kandidat
 * tetap ditanyakan ke SchedulePolicy, sehingga aturan yang menjadi lebih
 * ketat langsung berlaku di sini tanpa perlu menyentuh kelas ini. Tidak ada
 * kondisi role di kelas ini maupun di view.
 *
 * Batasnya perlu dinyatakan terang-terangan: karena penyaringan sudah
 * terjadi di baris `where`, policy pada titik ini hanya bisa mempersempit,
 * tidak pernah memperluas. Aturan `view()` yang kelak melebar — misalnya
 * admin juga melihat semua jadwal — tidak akan terpakai oleh query ini dan
 * harus ditangani di tempat lain.
 */
final class TodaySchedulesQuery
{
    /**
     * @return Collection<int,Schedule>
     */
    public function for(User $user, Carbon $tanggal): Collection
    {
        $hari = DayOfWeek::tryFrom((int) $tanggal->startOfDay()->dayOfWeekIso);

        if ($hari === null) {
            return new Collection;
        }

        $policy = app(SchedulePolicy::class);

        return Schedule::query()
            ->with(['schoolClass', 'subject'])
            ->where('teacher_id', $user->id)
            ->where('day_of_week', $hari)
            // `start_time` saja tidak menentukan urutan: dua jadwal dengan jam
            // mulai sama tidak punya urutan yang dijamin database, sehingga
            // list bisa berganti susunan antar-request. `id` sebagai pemutus
            // akhir membuat urutannya total dan stabil.
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->reject(fn (Schedule $schedule) => ! $policy->view($user, $schedule))
            ->values();
    }
}
