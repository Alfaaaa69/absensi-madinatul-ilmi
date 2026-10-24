# Jeda Jadwal Bebas per Hari

## Tujuan

Jeda (Upacara/Istirahat) bisa ditambah, diubah, dan dihapus sendiri oleh admin
untuk setiap hari Senin–Jumat, tanpa mengubah kode.

## Kondisi sekarang

- Jeda ditulis mati di `app/Support/SchoolBreaks.php` dan hanya berlaku Senin.
- Jeda dipakai di dua tempat: validasi simpan jadwal (`ScheduleManager`) dan
  tampilan grid (`ScheduleManagement`).
- Nomor jam pada grid Senin mengikuti tabel jam tetap di `SchoolBreaks::periodFor`
  (07:45 = jam 1, dst).

## Keputusan

- Tabel baru `school_breaks`: `id`, `day_of_week`, `label`, `start_time`,
  `end_time`, `after`, timestamps. `after` = jeda tampil setelah jam ke-N;
  `0` berarti sebelum jam 1.
- Migrasi mengisi 3 jeda Senin yang berlaku sekarang, jadi tampilan tidak
  berubah setelah deploy.
- Halaman baru **Master → Atur Jeda** (`/master/jeda`), khusus admin, dengan
  tombol pintasan dari halaman Jadwal. Berisi daftar jeda per hari dan form
  tambah/ubah/hapus.
- Grid tetap satu tabel. Baris jeda setelah jam ke-N menampilkan semua jeda
  dari hari mana pun yang posisinya sama, misalnya `Senin · Istirahat 10.05–10.35`
  dan `Rabu · Istirahat 09.30–09.45` dalam satu baris.
- `SchoolBreaks::periodFor` tidak diubah: jeda mengatur jam yang dilarang,
  bukan cara penomoran jam pelajaran.
- SchoolBreaks membaca database (`forDay`, `all`, `overlap`); tidak ada
  konfigurasi tersisa di kode selain data awal migrasi.

## Validasi

- Nama wajib, jam mulai < jam selesai, `after` bilangan 0–20.
- Jeda tidak boleh bertumpuk dengan jeda lain di hari yang sama.
- Jeda tidak boleh bertumpuk dengan jadwal yang sudah ada di hari itu (semua
  kelas), dan pesannya menyebut jadwal yang bentrok.
- Menghapus jeda selalu boleh.

## Tampilan

- Baris jeda tetap lebih tebal dan berwarna amber seperti sekarang.
- Hari tanpa jeda tidak menampilkan baris kosong.
- Halaman Atur Jeda memakai gaya tabel/form yang sama dengan halaman master
  lainnya.

## Pengujian

- Admin bisa tambah/ubah/hapus jeda; guru ditolak.
- Jeda yang menabrak jadwal atau jeda lain ditolak.
- Grid menampilkan jeda hari lain dan menyembunyikan hari tanpa jeda.
- Validasi jadwal memakai jam jeda dari database.
- Data awal Senin tetap sama setelah migrasi.

## Di luar cakupan

- Mengubah jam pelajaran per hari (tabel jam tetap Senin tetap di kode).
- Jeda berbeda per kelas atau per tahun ajaran.
- Riwayat/arsip perubahan jeda.
