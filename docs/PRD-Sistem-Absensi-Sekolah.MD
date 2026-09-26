# PRD — Sistem Absensi Sekolah (PKM)

## 1. Latar belakang
Sekolah mitra kegiatan PKM (Pengabdian kepada Masyarakat) masih menggunakan absensi manual berbasis buku. Proses ini rawan human error, rekap laporan butuh waktu lama, dan data historis sulit ditelusuri. Sistem ini dibangun untuk mendigitalisasi proses absensi harian di sekolah tersebut selama durasi program PKM.

## 2. Tujuan
- Mendigitalisasi pencatatan kehadiran siswa per jam pelajaran
- Mempercepat proses rekap dan pelaporan kehadiran
- Memberi visibilitas kehadiran siswa ke wali kelas dan admin sekolah
- Menjadi bukti transfer teknologi ke mitra sekolah sebagai bagian dari laporan PKM

## 3. Target pengguna & peran

| Peran | Deskripsi | Akses |
|---|---|---|
| Admin | Operator/staf tata usaha sekolah | Kelola data master (kelas, mapel, jadwal, siswa, user), lihat semua kelas |
| Guru | Mengajar satu atau lebih kelas | Input absensi sesuai jadwal mengajarnya sendiri, lihat rekap kehadiran bulanan untuk jadwal/kelas yang diajarnya |
| Wali kelas | Guru yang juga jadi penanggung jawab 1 kelas tertentu | Sama seperti guru, ditambah akses rekap harian & bulanan lintas mapel untuk kelas yang diwalikan |

Catatan: tidak ada tabel terpisah per peran. Semua pengguna ada di satu tabel `users`, dibedakan lewat kolom `role` yang bernilai `admin` atau `guru`. Wali kelas bukan role tersendiri — itu guru biasa yang `id`-nya ditunjuk sebagai `classes.homeroom_teacher_id`, dan statusnya dicek lewat relasi, bukan kolom role.

## 4. Lingkup (scope)

### 4.1 Masuk lingkup
- Manajemen data master: kelas, mata pelajaran, jadwal pelajaran, data siswa
- Input absensi oleh guru per jam pelajaran (status: Hadir / Izin / Sakit / Alpa)
- Dashboard guru: menampilkan jadwal mengajar hari itu, plus rekap bulanan kehadiran khusus untuk mata pelajaran yang dia ajar sendiri
- Dashboard wali kelas: tab "Jadwal saya" (sama seperti guru) + tab "Kelas saya" (rekap kehadiran seluruh siswa di kelas yang diwalikan, lintas semua mata pelajaran, dengan filter periode harian maupun bulanan)
- Rekap & laporan kehadiran per kelas/siswa/periode (admin: bebas pilih kelas manapun; guru: terbatas ke mapel yang diajar; wali kelas: terbatas ke kelas yang diwalikan)
- Export laporan ke Excel/PDF
- Role & hak akses berbasis `role` (admin, guru) dan kepemilikan wali kelas (`homeroom_teacher_id`)

### 4.2 Di luar lingkup (untuk versi ini)
- Login/akun khusus orang tua
- Notifikasi otomatis ke orang tua (WA/email/SMS)
- Face recognition / QR code scan (dicatat sebagai rekomendasi pengembangan lanjutan)

## 5. Alur pengguna utama

**Guru — input absensi harian**
1. Login
2. Lihat jadwal mengajar hari ini di dashboard
3. Pilih jadwal/kelas yang aktif
4. Tandai status kehadiran tiap siswa
5. Submit — data tersimpan

**Wali kelas — pantau kelas yang diwalikan**
1. Login
2. Buka tab "Kelas saya"
3. Pilih periode (harian atau bulanan)
4. Lihat tabel kehadiran siswa × mata pelajaran sesuai periode yang dipilih

**Guru — lihat rekap bulanan mapel yang diajar**
1. Login
2. Buka menu rekap di dashboard guru
3. Pilih bulan
4. Lihat rekap kehadiran siswa untuk mata pelajaran yang dia ajar

**Admin — kelola data & laporan**
1. Login
2. Kelola data master (kelas, mapel, jadwal, siswa)
3. Pilih periode & kelas (bebas semua kelas) di menu laporan
4. Lihat rekap, export ke Excel/PDF

## 6. Struktur data (ringkas)
Tabel utama: `users` (dengan kolom `role`), `classes` (dengan `homeroom_teacher_id`), `subjects`, `schedules`, `students`, `attendances`.

Relasi kunci:
- `schedules` menghubungkan `classes`, `subjects`, dan guru pengajar (`teacher_id`)
- `attendances` mencatat status kehadiran per `student`, per `schedule`, per tanggal, dicatat oleh siapa (`recorded_by`)
- `classes.homeroom_teacher_id` menandai wali kelas dari kelas tersebut

## 7. Metrik keberhasilan
- Waktu rekap laporan kehadiran berkurang dibanding proses manual (dari hitungan jam ke hitungan menit)
- Guru/staf sekolah mitra mampu menggunakan sistem secara mandiri setelah pelatihan (indikator keberhasilan transfer teknologi PKM)
- Data kehadiran tersimpan terstruktur dan bisa ditelusuri per periode

## 8. Tech stack
- Backend & frontend: Laravel + Livewire (starter kit resmi Laravel, bukan Jetstream/Breeze)
- Export laporan: Laravel Excel untuk Excel, library PDF terpisah untuk PDF
- Database: MySQL, primary key UUID
- Testing: Pest
- Auth: login memakai NUPTK, bukan email. Registrasi dimatikan, reset password lewat admin
- Deploy: shared hosting cPanel (PHP >= 8.2)

Skema database, role & scoping, dan batasan platform yang berlaku sudah dikunci di `docs/superpowers/specs/2026-09-26-sistem-absensi-sekolah-design.md`. Kalau dokumen ini berbeda dengan spec, **spec yang menang**.

## 9. Rekomendasi pengembangan lanjutan
- Portal login orang tua untuk memantau kehadiran anak
- Notifikasi otomatis (WA/email) saat siswa alpa
- Absensi via QR code atau face recognition

## 10. Task breakdown

Rincian ini sudah superseded oleh spec §9, yang mengunci skema, scope, dan estimasi. Yang berikut adalah versi yang sudah disesuaikan.

**Fase 0 — Prasyarat (0.5 hari)**
- Gate di cPanel: cek versi PHP (wajib >= 8.2) dan limit upload. **Stop bila PHP < 8.2** — tidak ada Laravel yang bisa jalan

**Fase 1 — Setup & fondasi (3.5 hari)**
- Setup project Laravel + Livewire starter kit, konfigurasi environment termasuk `APP_TIMEZONE=Asia/Jakarta` dan `QUEUE_CONNECTION=sync` (0.5 hari)
- Migration & model 7 tabel sesuai spec §3 (1 hari)
- Empat Policy (Schedule, Class, Student, Attendance) — **diuji dulu** (0.5 hari)
- `AttendanceRecorder` service, 3 Query Object rekap, `RecapExporter` (0.5 hari)
- Seeder data dummy: 1 admin, 5 guru, 1 tahun ajaran, 7 kelas, 8 mapel, ~35 jadwal, 200 siswa (0.5 hari)

**Fase 2 — Manajemen data master (4 hari)**
- CRUD tahun ajaran, kelas (+ assign wali kelas), mata pelajaran (1.5 hari)
- CRUD jadwal pelajaran + deteksi bentrok jam guru (1 hari)
- CRUD siswa + soft delete/restore, import Excel (1.5 hari)

**Fase 3 — Fitur inti absensi (4.5 hari)**
- Autentikasi: login via NUPTK, registrasi dimatikan, reset password via admin (0.5 hari)
- Dashboard guru, menampilkan jadwal hari ini (0.5 hari)
- Form input absensi + tandai-semua-hadir + kolom alasan kondisional (1.5 hari)
- Dashboard wali kelas, tab "Jadwal saya" + "Kelas saya" + drill-down per siswa (1.5 hari)
- Rekap bulanan guru untuk mapel yang diajar + validasi periode terhadap tahun ajaran (0.5 hari)

**Fase 4 — Laporan & rekap (3.5 hari)**
- Halaman laporan admin, filter periode & kelas (1 hari)
- Drill-down riwayat per siswa (0.5 hari)
- Export ke Excel & PDF dari sumber data yang sama dengan tampilan layar (1.5 hari)
- Halaman laporan wali kelas (0.5 hari)

**Fase 5 — Testing, deploy & dokumentasi (4 hari)**
- Test otomatis Pest: scoping per role, integritas batch absensi, validasi periode (2 hari)
- Setup hosting cPanel & deploy + dry-run di lokal (1 hari)
- SOP / manual penggunaan untuk sekolah mitra (0.5 hari)
- Dokumentasi untuk laporan PKM (0.5 hari)

Total estimasi: sekitar **20 hari kerja** (part-time, ~4-5 minggu kalender).

## 11. Catatan alur kerja dengan AI agent
Pengembangan dilakukan dengan bantuan AI coding agent, dengan pendekatan berikut:
- Dokumen ini (PRD), ERD, dan file migration dijadikan referensi context untuk AI agent, bukan dijelaskan ulang tiap sesi
- Task breakdown di atas dipecah lebih granular saat eksekusi — satu prompt untuk satu unit kerja kecil (misal satu CRUD), bukan satu fase sekaligus
- Urutan pengerjaan tetap mengikuti dependency: migration & model dulu, baru fitur yang bergantung padanya
- Setiap output AI agent direview manual sebelum lanjut ke task berikutnya, terutama logic query yang scope-nya sensitif terhadap role (misal query kelas milik wali kelas)
- Commit per task selesai untuk memudahkan rollback bila diperlukan
