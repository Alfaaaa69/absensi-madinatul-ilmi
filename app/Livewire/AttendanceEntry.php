<?php

namespace App\Livewire;

use App\Attendance\Queries\AdminContactsQuery;
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
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Input Absensi')]
class AttendanceEntry extends Component
{
    public Schedule $schedule;

    /**
     * Tanggal ini datang dari URL, dan URL tidak boleh bisa diubah
     * diam-diam dari snapshot klien. Tanpa `#[Locked]`, guru yang sedang
     * di halaman terkunci bisa mengirim `tanggal` berisi hari ini dan
     * mendapat baris `attendances` yang tanggalnya bertentangan dengan
     * halaman asalnya. Itu merusak audit trail tanpa memberi tanda. Aturan
     * jendela juga diturunkan dari nilai ini, jadi memalsainya berarti
     * `Gate::allows()` memeriksa tanggal yang salah.
     */
    #[Locked]
    public Carbon $tanggal;

    /**
     * Penanda apakah tanggal ini masih di dalam jendela edit. Ini hanya
     * untuk merender: apa yang ditampilkan view ditentukan flag ini, tapi
     * aturan jendela sendirinya dievaluasi ulang di `simpan()` lewat
     * `Gate::allows` — bukan dibaca dari sini, karena flag ini publik dan
     * bisa dikirim balik oleh klien.
     */
    #[Locked]
    public bool $bolehUbah = false;

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

        // Bukan `authorize`: halaman di luar jendela tidak lagi forbidden,
        // dia dirender sebagai halaman terkunci yang menjelaskan dirinya
        // sendiri. `Gate::authorize('recordAttendance', …)` yang dipakai di
        // sini sebelumnya membuat guru mendapat 403 tanpa penjelasan, dan
        // tanpa ide siapa yang bisa membantu. Roster dan absensi yang sudah
        // ada tetap dimuat di bawahnya; view yang tidak merendernya, supaya
        // `mount()` tidak jadi dua pekerjaan sekaligus: memutuskan dan
        // mengambil data.
        $this->bolehUbah = Gate::allows('recordAttendance', [$this->schedule, $this->tanggal]);

        // Sengaja whereDate, bukan where: cast `date` menulis `Y-m-d H:i:s` untuk
        // setiap nilai, sehingga kolom ini menyimpan jam 00:00:00 juga di SQLite.
        // MySQL memotongnya jadi DATE, tapi SQLite tidak — `where('date', 'Y-m-d')`
        // akan hijau di produksi dan merah di test. `whereDate` aman di keduanya.
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
    protected function rules(): array
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
    protected function messages(): array
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
     * Baris disusun dari persis `$status` yang dikirim. Keanggotaan kelas
     * tetap urusan `AttendanceRecorder` yang memuat roster sungguhan dari
     * `class_id`; yang diperiksa form hanya kelengkapan barisnya sendiri,
     * lewat `$baris` yang sudah dimuat `mount()`.
     */
    public function simpan(): void
    {
        $this->sukses = false;

        // Diturunkan ulang di sini, bukan dibaca dari `$bolehUbah`. Flag itu
        // publik, jadi nilainya bisa dikirim balik oleh klien; memakainya
        // sebagai penjaga berarti halaman terkunci bisa "dibuka" hanya dengan
        // mengarang satu field. `Gate::allows` menanyakan policy yang sama
        // pada detik yang sama, jadi hasilnya tidak bisa basi dan tidak bisa
        // dipalsukan. Pesannya juga kalimat jendela, bukan "kamu tidak
        // berwenang" dari `AttendanceRecorder` — yang itu menyalahkan orang
        // untuk aturan yang sebenarnya soal tanggal.
        if (! Gate::allows('recordAttendance', [$this->schedule, $this->tanggal])) {
            throw ValidationException::withMessages([
                'form' => $this->pesanTerkunci(),
            ]);
        }

        $this->validate();

        $this->pastikanBarisLengkap();

        $rows = new Collection($this->barisAbsensi());

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
     * Dua kalimat, dipilih dengan membandingkan tanggal saja. Tidak ada
     * `isAdmin()`, tidak ada pembacaan `role`: kalau suatu saat jendela edit
     * diperketat, admin yang kena batasnya tetap menerima kalimat yang sama
     * persis dengan yang diterima guru.
     *
     * Urutan pemeriksaan itu disengaja. Memeriksa masa depan lebih dulu
     * membuat fungsi ini benar untuk setiap tanggal apa pun tanggal itu, tanpa
     * bergantung pada pemanggil untuk menjaga agar cabang yang salah tidak
     * tercapai. Kalau "sudah lewat" diperiksa lebih dulu, tanggal hari ini
     * jatuh ke kalimat masa depan — benar sekarang hanya karena `bolehUbah`
     * bernilai `true` untuk hari ini, yaitu karena ada yang tidak boleh
     * bergantung.
     */
    public function pesanTerkunci(): string
    {
        if ($this->tanggal->isAfter(today()->endOfDay())) {
            return 'Absensi untuk tanggal ini belum bisa diisi.';
        }

        return 'Absensi ini sudah lewat 1 hari. Hubungi admin.';
    }

    /**
     * Kalimat umpan balik setelah simpan, dipilih dari tanggal lembar ini —
     * bukan dari `role` pemakai halaman.
     *
     * Alasannya Deliverable 3 Task 4. Kalimat sebelumnya selalu menjanjikan
     * lembar masih bisa diubah "selama jadwal hari ini atau kemarin masih
     * dibuka". Janji itu tidak berlaku untuk semua pembaca halaman yang sama:
     * admin tidak punya jendela satu hari sama sekali, jadi saat admin menyimpan
     * tanggal lima hari lalu, satu-satunya jalan memperbaikinya tetap lewat
     * admin — bukan lewat guru dalam satu hari.
     *
     * Yang dinyatakan di sini adalah *aturan*, bukan hak pembaca: kalau
     * tanggalnya masih di dalam jendela, kalimatnya menyebut batasnya; kalau
     * sudah lewat, kalimatnya menyatakan siapa yang masih bisa menutup selisih.
     * Dua kalimat itu sama-sama benar dibaca oleh siapa pun yang membuka
     * lembarnya, jadi tidak ada `isAdmin()` di sini — sama seperti
     * `pesanTerkunci()`.
     *
     * Cabang masa depan bukan tambahan kemewahan. `recordAttendance`
     * mengembalikan `true` untuk admin tanpa memeriksa tanggal, jadi admin
     * **bisa** menyimpan lembar yang tanggalnya belum tiba. Tanpa cabang ini,
     * lembar itu jatuh ke kalimat "masih bisa diubah sampai lewat 1 hari",
     * yang mengarang batas untuk tanggal yang belum mulai. Cabang masa depan
     * di `pesanTerkunci()` tidak bisa dipinjam mentah di sini, justru karena
     * bentuknya berbeda: policy membolehkan masa depan untuk admin, jadi
     * kehadiran tanggal itu tidak bisa diasumsikan mustahil.
     *
     * Urutan pemeriksaan sama seperti di `pesanTerkunci()`: masa depan dulu,
     * karena itu satu-satunya kondisi yang harus benar tanpa bergantung pada
     * siapa yang sedang membaca.
     */
    public function pesanTersimpan(): string
    {
        if ($this->tanggal->isAfter(today()->endOfDay())) {
            return 'Absensi tersimpan. Tanggal ini belum tiba, jadi status siswa masih bisa diubah lagi.';
        }

        if ($this->tanggal->isBefore(today()->subDay()->startOfDay())) {
            return 'Absensi tersimpan. Tanggal ini sudah lewat 1 hari, jadi perbaikannya hanya bisa dilakukan oleh admin.';
        }

        return 'Absensi tersimpan. Status siswa masih bisa diubah sampai jadwal ini lewat 1 hari.';
    }

    /**
     * Batch harus menutup seluruh baris form, bukan sebagian saja.
     * `mount()` sudah memuat roster ke `$this->baris`, jadi pemeriksaan ini
     * tidak query ulang dan tidak mengulang penjaga keanggotaan kelas milik
     * `AttendanceRecorder`.
     *
     * Tanpa pemeriksaan ini, request yang menyisakan satu siswa menulis
     * lembar absensi parsial sambil tetap menampilkan "Absensi tersimpan".
     * Pemulihan di halaman berikutnya bukan perbaikan netral: `mount()`
     * mengisi baris tanpa data dengan `hadir`, jadi pada simpanan guru
     * berikutnya siswa yang berlubang berubah diam-diam dari "tidak
     * tercatat" menjadi "hadir" — dan rekap menghitungnya hadir.
     *
     * Kunci kedua array dinormalisasi ke string lalu diurutkan supaya beda
     * tipe kunci (int dari JSON atau string dari mount) dan urutan pengiriman
     * tidak salah dibaca sebagai roster yang berbeda.
     */
    private function pastikanBarisLengkap(): void
    {
        $terkirim = array_map('strval', array_keys($this->status));
        $roster = array_map('strval', array_keys($this->baris));

        sort($terkirim);
        sort($roster);

        if ($terkirim === $roster) {
            return;
        }

        throw ValidationException::withMessages([
            'form' => 'Daftar siswa di form ini tidak lengkap. Muat ulang halaman lalu isi absensi dari awal.',
        ]);
    }

    /**
     * Bentuk baris dirakit di sini, bukan ditulis inline di `simpan()`,
     * supaya tipenya bisa dinyatakan. `Collection` bersifat invarian, jadi
     * `@param` service tidak bisa sempit selama pemanggil melaporkan
     * barisnya sebagai bentuk yang lebih longgar. Di posisi return
     * covariance berlaku, jadi `@return` di sini cukup untuk membuat call
     * site melihat `list<array<string, mixed>>` yang cocok dengan `@param`
     * service.
     *
     * Yang dijamin anotasi ini hanya itu: setiap baris adalah array
     * berkunci string. Keberadaan `student_id`, `status`, dan `note` yang
     * tercatat di `@param` service adalah dokumentasi, bukan jaminan yang
     * ditegakkan — `@return` yang dinyatakan di sini menggantikan bentuk
     * yang disimpulkan PHPStan, jadi menghapus salah satu kunci dari
     * array literal di bawah tidak akan reddenya. Service punya tiga
     * pembacaan array tanpa penjaga. Bentuk itu harus dijaga review mata,
     * bukan anotasi.
     *
     * @return list<array<string, mixed>>
     */
    private function barisAbsensi(): array
    {
        $rows = [];

        foreach (array_keys($this->status) as $id) {
            $rows[] = [
                'student_id' => $id,
                'status' => $this->status[$id],
                'note' => $this->noteUntuk($id),
            ];
        }

        return $rows;
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
            // Query ini hanya dibayar saat halamannya memang terkunci. Halaman
            // yang formnya terbuka tidak pernah membaca daftar admin, jadi
            // tidak perlu membayarnya di setiap request.
            'kontakAdmin' => $this->bolehUbah
                ? new Collection
                : app(AdminContactsQuery::class)->active(),
        ]);
    }
}
