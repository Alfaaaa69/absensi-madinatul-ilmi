# ERD — Sistem Absensi Sekolah

Skema database untuk sistem absensi sekolah (PKM). Rujuk juga ke `PRD-Sistem-Absensi-Sekolah.md` untuk konteks fitur.

> **Skema ini sudah di-update mengikuti `docs/specs/2026-09-26-sistem-absensi-sekolah-design.md`.** Diagram di bawah adalah ringkasan visual — bentuk kolom persisnya ada di spec §3 dan itulah yang dipakai saat menulis migration.

```mermaid
erDiagram
  ACADEMIC_YEARS ||--o{ CLASSES : "tahun ajaran dari"
  USERS ||--o{ SCHEDULES : mengajar
  USERS ||--o{ CLASSES : "wali kelas dari"
  USERS ||--o{ ATTENDANCES : mencatat
  USERS ||--o{ ATTENDANCES : "memperbarui"
  CLASSES ||--o{ SCHEDULES : punya
  SUBJECTS ||--o{ SCHEDULES : punya
  CLASSES ||--o{ STUDENTS : punya
  SCHEDULES ||--o{ ATTENDANCES : punya
  STUDENTS ||--o{ ATTENDANCES : punya

  USERS {
    uuid id PK
    string name
    string nuptk UK
    string email UK "nullable"
    string password
    string role "admin|guru"
    bool is_active
    timestamp deleted_at "soft delete"
  }
  ACADEMIC_YEARS {
    uuid id PK
    string name
    string period "ganjil|genap"
    date start_date
    date end_date
    bool is_active
  }
  CLASSES {
    uuid id PK
    uuid academic_year_id FK
    uuid homeroom_teacher_id FK "nullable"
    string name
    smallint grade_level
    timestamp deleted_at "soft delete"
  }
  SUBJECTS {
    uuid id PK
    string name
    string code "nullable"
    timestamp deleted_at "soft delete"
  }
  SCHEDULES {
    uuid id PK
    uuid class_id FK
    uuid subject_id FK
    uuid teacher_id FK
    tinyint day_of_week "1=Senin..5=Jumat"
    time start_time
    time end_time
    timestamp deleted_at "soft delete"
  }
  STUDENTS {
    uuid id PK
    uuid class_id FK
    string nisn UK
    string name
    string gender "L|P"
    timestamp deleted_at "soft delete"
  }
  ATTENDANCES {
    uuid id PK
    uuid student_id FK
    uuid schedule_id FK
    date date
    string status "hadir|izin|sakit|alpa"
    string note "nullable"
    uuid recorded_by FK
    uuid updated_by FK "nullable"
    timestamp created_at
    timestamp updated_at
  }
```

## Catatan relasi
- `USERS.role` bernilai `admin` atau `guru`. Wali kelas bukan role terpisah — ditandai lewat `CLASSES.homeroom_teacher_id` yang mengarah ke `USERS.id`.
- Login memakai `USERS.nuptk`, bukan `USERS.email`. Email nullable dan hanya untuk kontak/arsip.
- `ACADEMIC_YEARS` membatasi rentang tanggal rekap. `CLASSES` **wajib** punya `academic_year_id` — kelas dibuat ulang tiap tahun ajaran, sehingga kelas yang sama di tahun berbeda adalah baris berbeda.
- `SCHEDULES` adalah penghubung utama: satu baris jadwal = satu kombinasi kelas, mata pelajaran, guru pengajar, hari, dan jam.
- `CLASSES.homeroom_teacher_id` **nullable** — kelas boleh dibuat sebelum wali kelas ditunjuk.
- `ATTENDANCES` adalah tabel transaksi utama, satu baris = status kehadiran satu siswa pada satu jadwal di satu tanggal. Kombinasi `student_id + schedule_id + date` bersifat unik (dijaga lewat unique constraint di migration).
- `ATTENDANCES.recorded_by` mencatat guru mana yang menginput data tersebut (bisa berbeda dari `SCHEDULES.teacher_id` kalau ada kondisi guru pengganti). `updated_by` terisi hanya kalau baris itu pernah dikoreksi.
- `ATTENDANCES` **tidak** memakai soft delete. Koreksi dilakukan dengan mengubah `status` dan mengisi `updated_by`.
- Semua tabel master memakai soft delete, supaya menghapus data master tidak ikut menghapus riwayat absensi.
