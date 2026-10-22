# Jadwal Senin (transkrip dari jadwal sekolah)

Kelas: 7A, 7B, 8A, 8B, 9A, 9B

| Jam | Waktu | 7A | 7B | 8A | 8B | 9A | 9B |
|---|---|---|---|---|---|---|---|
| 0 | 07.30-07.45 | UPACARA (semua kelas) | | | | | |
| 1 | 07.45-08.20 | IPA | Ekstra. Ketr. Ukir | Ekstra. Science | B. Indonesia | PAKBP | IPS |
| 2 | 08.20-08.55 | IPA | B. Indonesia | Ekstra. Ketr. Ukir | Ekstra. Science | PAKBP | IPS |
| 3 | 08.55-09.30 | Ekstra. Ketr. Ukir | B. Indonesia | IPA | MTK | IPS | BK |
| 4 | 09.30-10.05 | B. Jawa | IPA | Seni Budaya | Prakarya | MTK | B. Inggris |
| | 10.05-10.35 | ISTIRAHAT | | | | | |
| 5 | 10.35-11.10 | B. Jawa | Literasi | Seni Budaya | Prakarya | MTK | B. Inggris |
| 6 | 11.10-11.45* | Pend. Pancasila | MTK | BK | B. Inggris | B. Indonesia | Ekstra. Ketr. Ukir |
| 7 | 11.45-12.20 | Pend. Pancasila | MTK | IPS | B. Inggris | B. Jawa | Seni Budaya |
| | 12.20-12.50 | ISTIRAHAT | | | | | |
| 8 | 12.50-13.25 | Seni & Prakarya | Pend. Pancasila | B. Inggris | IPS | B. Jawa | MTK |
| 9 | 13.25-14.00 | Seni & Prakarya | Pend. Pancasila | B. Inggris | IPS | Ekstra. Ketr. Ukir | MTK |

*Catatan: jam waktu di tabel asli untuk jam ke-6 tertulis "10.10 - 11.45" yang kemungkinan typo (tumpang tindih dengan jam ke-5). Diasumsikan 11.10-11.45 mengikuti pola jam ke-5 yang berakhir 11.10. Perlu dicek ulang ke sumber aslinya.

## Catatan cakupan kelas
Tabel di atas hanya mencakup kelas A dan B (7A, 7B, 8A, 8B, 9A, 9B). Sekolah ini sebenarnya punya kelas sampai C juga (7C, 8C, 9C), tapi datanya belum ada di tabel ini. Jangan buat asumsi jadwal kelas C mengikuti pola kelas A/B — tandai kelas C sebagai belum ada datanya, perlu ditambahkan terpisah nanti.

## Catatan mapping
Data `subjects` dan guru per mata pelajaran SUDAH ada di sistem. Nama mata pelajaran di tabel di atas tidak harus diikuti 100% persis — cocokkan ke mata pelajaran yang sudah ada berdasarkan kemiripan makna/nama (contoh: "B. Indonesia" di tabel = "Bahasa Indonesia" di `subjects`, "MTK" = "Matematika"). Kalau ada yang benar-benar tidak punya padanan di data yang sudah ada (misal kegiatan Ekstra, Devotion), tanyakan dulu sebelum menambah data baru, jangan langsung dibuatkan subject baru.

`teacher_id` diisi dari guru yang sudah terdaftar untuk mata pelajaran tersebut di data yang sudah ada — bukan placeholder baru.
