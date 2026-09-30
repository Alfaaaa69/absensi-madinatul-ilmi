<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Services\AttendanceRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Input Absensi')]
class AttendanceEntry extends Component
{
    public Schedule $schedule;

    public Carbon $tanggal;

    public bool $sudahDisimpan = false;

    /**
     * Penanda umpan balik setelah batch tersimpan. Dipisah dari
     * `sudahDisimpan` karena yang ini berarti "kamu baru saja menekan
     * simpan", sedangkan `sudahDisimpan` berarti "di halaman ini sudah ada
     * absensi sebelumnya" — keduanya sama-sama mengatur label tombol.
     */
    public bool $sukses = false;

    /**
     * @var array<string,array{nama:string,nisn:string,jk:string}>
     */
    public array $baris = [];

    /**
     * @var array<string,string>
     */
    public array $status = [];

    /**
     * @var array<string,string|null>
     */
    public array $note = [];

    /**
     * Nama parameter mount harus sama persis dengan parameter rute
     * (`tanggal_absensi`). Livewire mencocokkan berdasarkan nama: kalau
     * berubah jadi `tanggal`, rute berisi tanggal diam-diam datang sebagai
     * null dan halaman ini menampilkan hari ini — bug senyap tanpa error.
     */
    public function mount(Schedule $schedule, ?string $tanggal_absensi = null): void
    {
        $this->schedule = $schedule;
        $this->tanggal = $this->tanggalDari($tanggal_absensi);

        Gate::authorize('view', $this->schedule);
        Gate::authorize('recordAttendance', [$this->schedule, $this->tanggal]);

        // Sengaja whereDate, bukan where: cast `date` menulis `Y-m-d H:i:s` untuk
        // setiap nilai, sehingga kolom ini menyimpan jam 00:00:00 juga di SQLite.
        // MySQL memotongnya jadi DATE, tapi SQLite tidak — `where('date', 'Y-m-d')`
        // akan hijau di produksi dan merah di test.whereDate aman di keduanya.
        $tersimpan = Attendance::query()
            ->where('schedule_id', $this->schedule->id)
            ->whereDate('date', $this->tanggal->toDateString())
            ->get(['student_id', 'status', 'note'])
            ->keyBy('student_id');

        $this->sudahDisimpan = $tersimpan->isNotEmpty();

        $siswa = $this->schedule->schoolClass->students()->orderBy('name')->get();

        foreach ($siswa as $orang) {
            $absensi = $tersimpan->get($orang->id);

            $this->baris[$orang->id] = [
                'nama' => $orang->name,
                'nisn' => $orang->nisn,
                'jk' => $orang->gender->value,
            ];

            $this->status[$orang->id] = $absensi?->status->value ?? AttendanceStatus::Hadir->value;
            $this->note[$orang->id] = $absensi?->note;
        }
    }

    /**
     * Isi seluruh baris sekaligus. Alasan lama ikut dibersihkan karena
     * hanya relevan selama statusnya bukan hadir — membiarkan sisa
     * "Demam sejak kemarin" menempel pada baris yang ditandai hadir.
     */
    public function tandaiSemua(string $status): void
    {
        // Argumen action publik Livewire datang dari snapshot klien, bukan
        // hanya dari dua nilai yang ditulis di `wire:click`. Status asing
        // diabaikan, bukan dilempar: `from()` akan melempar \ValueError dan
        // satu permintaan yang direkayasa membuat halaman 500. Ini bukan
        // kontrol keamanan — simpan() memvalidasi ulang semua saat menyimpan.
        $pilihan = AttendanceStatus::tryFrom($status);

        if ($pilihan === null) {
            return;
        }

        foreach (array_keys($this->baris) as $id) {
            $this->status[$id] = $pilihan->value;
        }

        $this->note = [];
    }

    /**
     * Alasan hanya relevan untuk izin, sakit, dan alpa. Saat guru
     * mengembalikan status ke hadir, alasan lama dibuang supaya tidak
     * ikut tersimpan sebagai sisa.
     *
     * `$key` boleh null: ketika seluruh array `status` diganti dalam satu
     * permintaan, Livewire memanggil hook ini sekali tanpa kunci baris.
     * Signature `string` yang sempit membuat \TypeError sebelum `simpan()`
     * sempat bekerja — halaman 500 untuk permintaan yang sebenarnya tidak
     * nakal.
     */
    public function updatedStatus(mixed $value, ?string $key): void
    {
        if ($key === null) {
            return;
        }

        if ($value !== AttendanceStatus::Hadir->value) {
            return;
        }

        unset($this->note[$key]);
    }

    public function alasanTerlihat(string $studentId): bool
    {
        return ($this->status[$studentId] ?? AttendanceStatus::Hadir->value)
            !== AttendanceStatus::Hadir->value;
    }

    /**
     * Validasi bentuk payload yang masuk dari snapshot klien. Yang diperiksa
     * hanya apa yang benar-benar dikirim: seluruh baris yang ada di
     * `status` harus punya status yang dikenal. Keanggotaan kelas bukan
     * urusan form — `AttendanceRecorder` yang memuat roster sungguhan dan
     * menolak baris asing, jadi pemeriksaan kedua di sini hanya mengulang
     * penjaga yang sama di tempat lain dan bisa melenceng.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'array'],
            'status.*' => ['required', Rule::enum(AttendanceStatus::class)],
            'note' => ['nullable', 'array'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status kehadiran wajib diisi.',
            'status.array' => 'Status kehadiran wajib diisi.',
            'status.*.required' => 'Status kehadiran siswa ini wajib dipilih.',
            'status.*.enum' => 'Status kehadiran harus salah satu dari: hadir, izin, sakit, alpa.',
            'note.array' => 'Data alasan tidak valid.',
            'note.*.string' => 'Alasan harus berupa teks.',
            'note.*.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    /**
     * Satu-satunya jalan component ini ke database: semua tulis lewat
     * `AttendanceRecorder`, yang membungkus autorisasi dan seluruh batchnya
     * dalam satu `DB::transaction`. Karena itu component tidak memanggil
     * `Gate::authorize` sendiri — aturan yang diulang di dua tempat bisa
     * berbeda, dan yang dilupakan biasanya yang kedua.
     *
     * Baris disusun dari persis `$status` yang dikirim, tanpa penyaringan
     * roster. Keanggotaan kelas adalah urusan `AttendanceRecorder` yang
     * memuat roster sungguhan dari `class_id`, jadi daftar siswa tidak
     * pernah dihitung di sini: penjaga yang diulang di dua tempat bisa
     * melenceng, dan yang melenceng biasanya yang tidak diuji.
     */
    public function simpan(): void
    {
        $this->validate();

        /**
         * Bentuk di bawah menyatakan kontrak yang diterima `record()`, bukan
         * tebakan isi array: `Collection` bersifat invarian, sehingga bentuk
         * yang lebih longgar dari kontrak itu ditolak analisis statis —
         * dan kontrak `record()` sendiri tidak bisa diturunkan dari
         * inferensi, karena `status` selalu string dari snapshot dan
         * `note` selalu ada. Status diteruskan apa adanya; `record()` yang
         * memvalidasi ulang lewat `AttendanceStatus::from()`.
         *
         * @var Collection<int, array{student_id: string, status: AttendanceStatus|string, note?: string|null}>
         */
        $rows = new Collection(collect(array_keys($this->status))->map(fn (string $id) => [
            'student_id' => $id,
            'status' => $this->status[$id],
            'note' => $this->noteUntuk($id),
        ])->all());

        try {
            app(AttendanceRecorder::class)->record(
                $this->schedule,
                $this->tanggal,
                $rows,
                auth()->user(),
            );
        } catch (AuthorizationException) {
            throw ValidationException::withMessages([
                'form' => 'Kamu tidak berwenang mengisi absensi jadwal ini. Hubungi admin.',
            ]);
        } catch (ValidationException $penolakan) {
            throw ValidationException::withMessages($penolakan->errors() + [
                'form' => 'Absensi gagal disimpan. Tidak ada satu pun data yang tersimpan.',
            ]);
        }

        $this->sudahDisimpan = true;
        $this->sukses = true;
    }

    /**
     * Alasan hanya disimpan bersama status izin, sakit, atau alpa.
     * Status hadir selalu ditulis dengan alasan kosong, walaupun
     * masih ada sisa teks di field.
     */
    private function noteUntuk(string $studentId): ?string
    {
        if (($this->status[$studentId] ?? '') === AttendanceStatus::Hadir->value) {
            return null;
        }

        $catatan = trim((string) ($this->note[$studentId] ?? ''));

        return $catatan === '' ? null : $catatan;
    }

    /**
     * Tanggal terkunci. Nilai dari rute adalah satu-satunya sumber selain
     * hari ini. Bentuknya harus persis Y-m-d supaya tidak ada tanggal liar
     * yang lolos ke query — dan `hasFormat` saja tidak cukup, karena ia
     * hanya mengecek bentuk: `2026-02-30` lolos lalu `Carbon::parse`
     * melanjutkannya ke 2 Maret tanpa error. Karena itu tanggal hasil parse
     * harus masih sama persis dengan masukannya, kalau tidak 404.
     */
    private function tanggalDari(?string $tanggal): Carbon
    {
        if ($tanggal === null) {
            return now()->startOfDay();
        }

        if (! Carbon::hasFormat($tanggal, 'Y-m-d')) {
            abort(404);
        }

        $parsed = Carbon::parse($tanggal, 'Asia/Jakarta')->startOfDay();

        if ($parsed->toDateString() !== $tanggal) {
            abort(404);
        }

        return $parsed;
    }

    public function render(): View
    {
        return view('livewire.attendance-entry', [
            'tanggal' => $this->tanggal,
        ]);
    }
}
