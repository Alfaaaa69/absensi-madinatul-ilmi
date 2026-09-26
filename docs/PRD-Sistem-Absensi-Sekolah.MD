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

Catatan: tidak ada peran/akun terpisah untuk admin — dibedakan lewat kolom `role` pada tabel `users` yang sama dengan guru.

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
- Backend & frontend: Laravel + Livewire
- Export laporan: Laravel Excel
- Database: sesuai konvensi Laravel (MySQL/PostgreSQL), primary key UUID

## 9. Rekomendasi pengembangan lanjutan
- Portal login orang tua untuk memantau kehadiran anak
- Notifikasi otomatis (WA/email) saat siswa alpa
- Absensi via QR code atau face recognition

## 10. Task breakdown

**Fase 1 — Setup & fondasi (3-4 hari)**
- Setup project Laravel + Livewire, konfigurasi environment (0.5 hari)
- Migration & model semua tabel sesuai ERD (1 hari)
- Autentikasi (login, role admin/guru) (1 hari)
- Seeder data dummy untuk testing (0.5 hari)

**Fase 2 — Manajemen data master (3-4 hari)**
- CRUD kelas (0.5 hari)
- CRUD mata pelajaran (0.5 hari)
- CRUD jadwal pelajaran, relasi kelas-mapel-guru (1 hari)
- CRUD siswa, termasuk assign wali kelas ke kelas (1 hari)

**Fase 3 — Fitur inti absensi (5-6 hari)**
- Dashboard guru, menampilkan jadwal hari ini (1 hari)
- Form input absensi per jadwal, Livewire component (1.5 hari)
- Validasi & logic submit absensi (0.5 hari)
- Dashboard wali kelas, tab "Jadwal saya" + "Kelas saya" (1.5 hari)
- Filter periode harian/bulanan di tab "Kelas saya" wali kelas, dan rekap bulanan guru untuk mapel yang diajar (1 hari)

**Fase 4 — Laporan & rekap (2-3 hari)**
- Halaman rekap admin, filter periode & kelas (1 hari)
- Export ke Excel/PDF dengan Laravel Excel (1 hari)

**Fase 5 — Testing, deploy & dokumentasi (3-4 hari)**
- Testing manual seluruh flow: guru, wali kelas, admin (1 hari)
- Setup hosting & deploy (0.5 hari)
- Pelatihan penggunaan ke guru/staf sekolah mitra (0.5 hari)
- Dokumentasi untuk laporan PKM (1 hari)

Total estimasi: sekitar 16-21 hari kerja (part-time, ~4-6 minggu kalender).

## 11. Catatan alur kerja dengan AI agent
Pengembangan dilakukan dengan bantuan AI coding agent, dengan pendekatan berikut:
- Dokumen ini (PRD), ERD, dan file migration dijadikan referensi context untuk AI agent, bukan dijelaskan ulang tiap sesi
- Task breakdown di atas dipecah lebih granular saat eksekusi — satu prompt untuk satu unit kerja kecil (misal satu CRUD), bukan satu fase sekaligus
- Urutan pengerjaan tetap mengikuti dependency: migration & model dulu, baru fitur yang bergantung padanya
- Setiap output AI agent direview manual sebelum lanjut ke task berikutnya, terutama logic query yang scope-nya sensitif terhadap role (misal query kelas milik wali kelas)
- Commit per task selesai untuk memudahkan rollback bila diperlukan
