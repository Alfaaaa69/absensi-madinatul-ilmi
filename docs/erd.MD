# ERD — Sistem Absensi Sekolah

Skema database untuk sistem absensi sekolah (PKM). Rujuk juga ke `PRD-Sistem-Absensi-Sekolah.md` untuk konteks fitur.

```mermaid
erDiagram
  USERS ||--o{ SCHEDULES : mengajar
  USERS ||--o| CLASSES : "wali kelas dari"
  CLASSES ||--o{ SCHEDULES : punya
  SUBJECTS ||--o{ SCHEDULES : punya
  CLASSES ||--o{ STUDENTS : punya
  SCHEDULES ||--o{ ATTENDANCES : punya
  STUDENTS ||--o{ ATTENDANCES : punya
  USERS ||--o{ ATTENDANCES : mencatat

  USERS {
    uuid id PK
    string name
    string email
    string role
  }
  CLASSES {
    uuid id PK
    uuid homeroom_teacher_id FK
    string name
  }
  SUBJECTS {
    uuid id PK
    string name
  }
  SCHEDULES {
    uuid id PK
    uuid class_id FK
    uuid subject_id FK
    uuid teacher_id FK
    string day
    time start_time
    time end_time
  }
  STUDENTS {
    uuid id PK
    uuid class_id FK
    string name
    string nisn
  }
  ATTENDANCES {
    uuid id PK
    uuid student_id FK
    uuid schedule_id FK
    date date
    string status
    uuid recorded_by FK
    timestamp created_at
  }
```

## Catatan relasi
- `USERS.role` bernilai `admin` atau `guru`. Wali kelas bukan role terpisah — ditandai lewat `CLASSES.homeroom_teacher_id` yang mengarah ke `USERS.id`.
- `SCHEDULES` adalah penghubung utama: satu baris jadwal = satu kombinasi kelas, mata pelajaran, guru pengajar, hari, dan jam.
- `ATTENDANCES` adalah tabel transaksi utama, satu baris = status kehadiran satu siswa pada satu jadwal di satu tanggal. Kombinasi `student_id + schedule_id + date` bersifat unik (dijaga lewat unique constraint di migration).
- `ATTENDANCES.recorded_by` mencatat guru mana yang menginput data tersebut (bisa berbeda dari `SCHEDULES.teacher_id` kalau ada kondisi guru pengganti).
