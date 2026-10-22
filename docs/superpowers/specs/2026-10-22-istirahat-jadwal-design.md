# Istirahat Terkunci pada Jadwal

## Tujuan

Menampilkan dan mengunci dua jeda istirahat pada jadwal Senin supaya slot
tersebut tidak bisa dipakai untuk mata pelajaran.

## Sumber

`docs/jadwal-senin.md`:

- 10.05–10.35 — Istirahat (setelah jam 4)
- 12.20–12.50 — Istirahat (setelah jam 7)

Jadwal asli yang tersedia baru untuk hari Senin.

## Keputusan

- Jeda hanya berlaku untuk **Senin**. Selasa–Jumat tidak diubah karena
  struktur jamnya berbeda dan belum ada jadwal sumbernya.
- Tidak menambah tabel atau model baru. Aturan jeda disimpan sebagai
  konfigurasi kecil di kode.
- Grid menampilkan dua baris jeda setelah Jam 4 dan Jam 7.
- Validasi menolak jadwal Senin yang tumpang tindih dengan jeda.
- Pesan error menyebut jam dan nama jeda.

## Perilaku

- Jadwal Senin 10.05–10.35 atau 12.20–12.50 ditolak.
- Jadwal Senin yang berakhir tepat 10.05 atau mulai tepat 10.35 diterima.
- Jadwal Selasa–Jumat tidak terpengaruh.
- Jadwal lama tidak dihapus atau diubah.

## Tampilan

Dua baris jeda di grid:

- Istirahat · 10.05–10.35
- Istirahat / Sholat · 12.20–12.50

Baris dibuat lebih redup, membentang penuh, dan diberi label **Senin** agar
tidak terkesan berlaku untuk semua hari.

## Pengujian

- Jadwal Senin yang menabrak jeda ditolak.
- Jadwal Senin yang berhenti tepat di batas jeda diterima.
- Jadwal Selasa pada jam yang sama tetap diterima.
- Baris jeda tampil di halaman jadwal.

## Di luar cakupan

- Belum mengatur jeda Selasa–Jumat.
- Belum membuat CRUD atau tabel khusus istirahat.
