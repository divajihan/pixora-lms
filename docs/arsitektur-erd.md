# Rancangan Arsitektur & ERD: LMS Kursus Online

Tanggal: 2026-11-13 · Status: draf 1 (untuk direview)

## 1. Ringkasan, asumsi, dan keputusan teknis

**Produk:** LMS untuk satu penyedia kursus online umum. Siswa mendaftar sendiri, membeli kursus (atau mengambil kursus gratis), belajar lewat video + rangkuman teks, mengumpulkan tugas, dan mendapat sertifikat otomatis.

**Asumsi (ubah jika salah):**
- Skala: 500 - 5.000 siswa aktif di tahun pertama.
- Satu klien, bukan multi-tenant. Branding lewat design token (CSS variables), bukan sistem tema dinamis.
- Payment gateway: Midtrans (Snap) lewat lapisan abstraksi sehingga bisa diganti Xendit.
- Video di YouTube (unlisted), diputar lewat embed. Lapisan video dipisah agar mudah diganti Bunny/Vimeo.
- Live class: tautan dan jadwal diisi manual (tanpa API Zoom/Meet).
- Kuis, diskusi, kupon, dan bundle ditunda ke tahap 2.

**Stack:**

| Lapisan | Pilihan |
|---|---|
| Backend | Laravel (versi stabil terbaru), PHP 8.3+ |
| Frontend | Inertia + React + shadcn/ui + Tailwind (starter kit resmi Laravel) |
| Database | MySQL 8 |
| Cache, queue, session | Redis |
| Storage file | Disk privat (lokal atau S3-compatible) dengan signed URL |
| Auth | Session (Fortify dari starter kit), verifikasi email |
| Role/permission | spatie/laravel-permission |
| PDF sertifikat | barryvdh/laravel-dompdf (atau Browsershot jika desain kompleks) |
| Log aktivitas | spatie/laravel-activitylog (opsional) |

## 2. Arsitektur sistem

Monolit modular: satu aplikasi Laravel, dipisah per modul di level folder. Ini cukup untuk skala target dan jauh lebih mudah dirawat sendirian dibanding microservices.

```mermaid
flowchart LR
  subgraph Client
    B[Browser: React + shadcn/ui via Inertia]
  end
  subgraph Server[VPS]
    N[Nginx + PHP-FPM]
    A[Laravel App\nControllers, Actions, Policies]
    Q[Queue Worker]
    S[Scheduler cron]
    R[(Redis\ncache, queue, session)]
    D[(MySQL)]
    F[(Storage privat\nfile tugas, sertifikat)]
  end
  subgraph External
    M[Midtrans Snap + Webhook]
    Y[YouTube embed]
    E[SMTP / email service]
  end
  B --> N --> A
  A --> D
  A --> R
  A --> F
  Q --> D
  Q --> F
  Q --> E
  S --> A
  A <--> M
  B --> Y
```

**Komponen dan tanggung jawab:**
- **Laravel App:** controller tipis, logika bisnis di `Actions` (kelas satu tugas, contoh `EnrollStudent`, `ComputeLessonAccess`, `IssueCertificate`). Otorisasi lewat Policy.
- **Queue worker:** kirim email, buat PDF sertifikat, proses webhook berat, notifikasi lesson baru.
- **Scheduler:** pengingat lesson baru terbuka, tandai enrollment kedaluwarsa, bersihkan order pending yang kedaluwarsa.
- **Redis:** queue, cache (katalog, hitung akses), dan session.
- **Storage privat:** file tugas siswa dan gambar feedback tidak pernah publik; diakses lewat signed URL berumur pendek.
- **Midtrans:** Snap untuk pembayaran, webhook untuk konfirmasi (diverifikasi signature dan idempoten).
- **YouTube:** hanya embed. ID video baru dikirim ke frontend jika lesson sudah terbuka untuk siswa itu.

## 3. Struktur modul Laravel dan otorisasi

```
app/
  Actions/           # satu kelas = satu use case
    Enrollment/  Payment/  Learning/  Assignment/  Certificate/
  Enums/             # CourseStatus, OrderStatus, SubmissionStatus, dst.
  Http/Controllers/
    Public/  Student/  Instructor/  Admin/  Webhook/
  Models/
  Policies/          # CoursePolicy, LessonPolicy, SubmissionPolicy, OrderPolicy
  Services/
    Payment/PaymentGateway.php (interface) + MidtransGateway.php
    Video/VideoProvider.php (interface) + YouTubeProvider.php
  Jobs/  Notifications/  Console/
resources/js/Pages/   # Public, Student, Instructor, Admin
```

**Peran dan hak akses:**

| Peran | Hak utama |
|---|---|
| Admin | Semua: kursus, pengguna, order, laporan, pengaturan |
| Staf | Lihat/kelola order dan konfirmasi pembayaran manual, bantu siswa, moderasi; tidak mengubah konten kursus |
| Instruktur | Hanya kursus yang ditugaskan: atur pembukaan lesson, lihat siswa dan progres, periksa tugas, beri feedback |
| Siswa | Beli/ambil kursus, belajar lesson yang terbuka, kumpulkan tugas, unduh sertifikat sendiri |

**Halaman utama:**
- Publik: beranda, katalog, detail kursus, login/daftar, verifikasi sertifikat (`/verify/{kode}`).
- Siswa: dashboard, kursus saya, player lesson, tugas saya, riwayat order, sertifikat.
- Instruktur: kursus saya, daftar siswa, pengaturan pembukaan lesson, antrean tugas masuk, form feedback.
- Admin/staf: CRUD kursus/modul/lesson, pengguna, order/pembayaran, laporan, pengaturan (branding, sertifikat).

## 4. Alur utama

**A. Beli kursus berbayar**
1. Siswa klik beli, sistem membuat `orders` (pending) + `order_items` (harga disalin sebagai snapshot).
2. Server meminta Snap token ke Midtrans, siswa membayar.
3. Webhook Midtrans masuk. Sistem memverifikasi signature, mencatat di `webhook_events` (unik per event, agar idempoten), lalu dalam satu transaksi database: update `payments`, set order `paid`, buat `enrollments` (status `active`, `enrolled_at = now`).
4. Notifikasi email dikirim lewat queue.

**B. Kursus gratis:** siswa klik ambil, langsung dibuat `enrollments` tanpa order (`order_id` null).

**C. Belajar dengan drip (gabungan)**

Waktu buka sebuah lesson dihitung saat dibaca, tanpa job penjadwal:
1. Cari override untuk enrollment ini dan lesson ini (`lesson_unlock_overrides.enrollment_id = X`). Jika ada, pakai.
2. Jika tidak, cari override seluruh kelas (`enrollment_id IS NULL`) untuk lesson itu. Jika ada, pakai.
3. Jika tidak ada, pakai default: `enrollments.enrolled_at + lessons.drip_day_offset hari`.
4. Override bisa mengatur `unlock_at` (buka lebih awal atau tunda) atau `is_locked = true` (kunci paksa).
5. Lesson dapat diakses jika enrollment aktif, belum kedaluwarsa, lesson berstatus published, dan waktu buka sudah lewat.

Siswa menandai lesson selesai (atau otomatis di ujung video), tercatat di `lesson_progress`. Persentase progres di-cache di `enrollments.progress_percent`.

**D. Tugas dan feedback**
1. Siswa mengunggah jawaban (teks + file) pada lesson atau proyek akhir, dibuat `submissions` (status `submitted`).
2. Instruktur melihat antrean, memberi feedback tulisan dan/atau gambar (`submission_feedback` + `attachments`), lalu menetapkan status: `approved` atau `needs_revision`.
3. Jika `needs_revision`, siswa mengirim ulang sebagai percobaan baru (`attempt_no` naik).
4. Siswa dinotifikasi setiap ada feedback.

**E. Sertifikat**
1. Setelah lesson terakhir selesai atau tugas wajib disetujui, sistem mengecek syarat kelulusan.
2. Jika terpenuhi, job `IssueCertificate` membuat nomor dan kode verifikasi, membuat PDF, menyimpan ke `certificates`, dan menandai enrollment `completed`. Kolom `enrollment_id` yang unik mencegah sertifikat ganda meski job berjalan dua kali.

## 5. Diagram ERD

```mermaid
erDiagram
  users ||--o| instructor_profiles : has
  users ||--o{ course_instructors : teaches
  courses ||--o{ course_instructors : has
  categories ||--o{ courses : groups
  courses ||--o{ modules : contains
  modules ||--o{ lessons : contains
  courses ||--o{ lessons : has
  lessons ||--o| live_sessions : schedules
  users ||--o{ enrollments : enrolls
  courses ||--o{ enrollments : has
  orders |o--o{ enrollments : grants
  enrollments ||--o{ lesson_progress : tracks
  lessons ||--o{ lesson_progress : tracked_in
  lessons ||--o{ lesson_unlock_overrides : overridden_by
  enrollments |o--o{ lesson_unlock_overrides : scoped_to
  courses ||--o{ assignments : has
  lessons |o--o{ assignments : attached_to
  assignments ||--o{ submissions : receives
  enrollments ||--o{ submissions : submits
  submissions ||--o{ submission_feedback : gets
  users ||--o{ submission_feedback : writes
  users ||--o{ orders : places
  orders ||--o{ order_items : has
  courses ||--o{ order_items : sold_as
  orders ||--o{ payments : paid_by
  enrollments ||--o| certificates : earns

  users {
    bigint id PK
    string name
    string email UK
    string password
    string phone
    boolean is_active
    datetime email_verified_at
  }
  instructor_profiles {
    bigint id PK
    bigint user_id FK
    string headline
    text bio
  }
  categories {
    bigint id PK
    string name
    string slug UK
  }
  courses {
    bigint id PK
    bigint category_id FK
    string title
    string slug UK
    int price
    boolean is_free
    string status
    boolean drip_enabled
    int access_duration_days
    boolean certificate_enabled
  }
  course_instructors {
    bigint id PK
    bigint course_id FK
    bigint user_id FK
    string role
  }
  modules {
    bigint id PK
    bigint course_id FK
    string title
    int position
  }
  lessons {
    bigint id PK
    bigint course_id FK
    bigint module_id FK
    string title
    string type
    string video_ref
    longtext summary_html
    int drip_day_offset
    int position
    string status
    boolean is_preview
  }
  live_sessions {
    bigint id PK
    bigint lesson_id FK
    datetime starts_at
    datetime ends_at
    string meeting_url
  }
  enrollments {
    bigint id PK
    bigint user_id FK
    bigint course_id FK
    bigint order_id FK
    string status
    datetime enrolled_at
    datetime expires_at
    datetime completed_at
    tinyint progress_percent
  }
  lesson_unlock_overrides {
    bigint id PK
    bigint lesson_id FK
    bigint enrollment_id FK
    datetime unlock_at
    boolean is_locked
    bigint created_by FK
  }
  lesson_progress {
    bigint id PK
    bigint enrollment_id FK
    bigint lesson_id FK
    string status
    datetime completed_at
  }
  assignments {
    bigint id PK
    bigint course_id FK
    bigint lesson_id FK
    string title
    boolean is_final_project
    boolean is_required
  }
  submissions {
    bigint id PK
    bigint assignment_id FK
    bigint enrollment_id FK
    int attempt_no
    text body
    string status
    datetime submitted_at
    datetime reviewed_at
    bigint reviewed_by FK
  }
  submission_feedback {
    bigint id PK
    bigint submission_id FK
    bigint instructor_id FK
    text body
  }
  orders {
    bigint id PK
    bigint user_id FK
    string order_number UK
    int total
    string status
    datetime paid_at
    datetime expires_at
  }
  order_items {
    bigint id PK
    bigint order_id FK
    bigint course_id FK
    string title_snapshot
    int price
  }
  payments {
    bigint id PK
    bigint order_id FK
    string gateway
    string external_id UK
    string payment_type
    int gross_amount
    string status
    json raw_payload
  }
  certificates {
    bigint id PK
    bigint enrollment_id FK
    string certificate_number UK
    string verification_code UK
    string pdf_path
    datetime issued_at
  }
```

Tabel pendukung yang tidak digambar agar diagram tetap terbaca: `attachments` (polimorfik, untuk file submission dan gambar feedback), `webhook_events`, `settings`, `notifications` (bawaan Laravel), dan tabel paket spatie (`roles`, `permissions`, dst.).

## 6. Kamus tabel

Semua tabel memakai `id` bigint unsigned auto-increment, `created_at`, `updated_at`. Harga disimpan sebagai integer rupiah (tanpa desimal). Kolom `status` memakai string yang dipetakan ke Enum PHP.

### Inti pengguna dan katalog
| Tabel | Kolom penting | Catatan / indeks |
|---|---|---|
| `users` | name, email (unik), password, phone, avatar_path, is_active, email_verified_at | Role lewat spatie |
| `instructor_profiles` | user_id (unik), headline, bio | Tampil di halaman kursus |
| `categories` | name, slug (unik), parent_id (null) | |
| `courses` | category_id, title, slug (unik), summary, description, thumbnail_path, price, is_free, status (`draft`/`published`/`archived`), drip_enabled, access_duration_days (null = selamanya), certificate_enabled, published_at, created_by | Indeks (status, published_at) |
| `course_instructors` | course_id, user_id, role (`lead`/`assistant`) | Unik (course_id, user_id); dipakai Policy "instruktur kursus ini" |

### Kurikulum
| Tabel | Kolom penting | Catatan / indeks |
|---|---|---|
| `modules` | course_id, title, position | Indeks (course_id, position) |
| `lessons` | course_id, module_id, title, slug, type (`video`/`live`), video_provider, video_ref, duration_seconds, summary_html, drip_day_offset (hari ke-N sejak mulai; 0 = langsung), position, status, is_preview | Indeks (course_id, position); `video_ref` jangan dikirim ke frontend jika lesson terkunci |
| `live_sessions` | lesson_id (unik), starts_at, ends_at, platform, meeting_url, notes | Diisi manual |

### Akses dan progres
| Tabel | Kolom penting | Catatan / indeks |
|---|---|---|
| `enrollments` | user_id, course_id, order_id (null untuk gratis), status (`active`/`completed`/`expired`/`revoked`), enrolled_at, expires_at, completed_at, progress_percent | Unik (user_id, course_id); indeks (course_id, status) |
| `lesson_unlock_overrides` | lesson_id, enrollment_id (null = seluruh kelas), unlock_at (null jika hanya kunci), is_locked, created_by | Unik (lesson_id, enrollment_id) berlaku untuk override per siswa. MySQL mengizinkan banyak NULL di indeks unik, jadi override seluruh kelas (enrollment_id NULL) harus dijaga unik di aplikasi (`updateOrCreate`) atau lewat kolom generated `scope_key = COALESCE(enrollment_id, 0)` yang di-unique bersama lesson_id. Prioritas: per siswa > seluruh kelas > default |
| `lesson_progress` | enrollment_id, lesson_id, status (`in_progress`/`completed`), watched_seconds, completed_at | Unik (enrollment_id, lesson_id) |

### Tugas
| Tabel | Kolom penting | Catatan / indeks |
|---|---|---|
| `assignments` | course_id, lesson_id (null untuk proyek akhir), title, instructions_html, is_final_project, is_required, allow_images | Indeks (course_id, lesson_id) |
| `submissions` | assignment_id, enrollment_id, attempt_no, body, status (`submitted`/`needs_revision`/`approved`), submitted_at, reviewed_at, reviewed_by | Unik (assignment_id, enrollment_id, attempt_no); indeks (assignment_id, status) untuk antrean instruktur |
| `submission_feedback` | submission_id, instructor_id, body | Bisa lebih dari satu per submission |
| `attachments` | attachable_type, attachable_id, disk, path, original_name, mime, size, uploaded_by | Polimorfik ke submission dan feedback; disk privat |

### Pembayaran
| Tabel | Kolom penting | Catatan / indeks |
|---|---|---|
| `orders` | user_id, order_number (unik), subtotal, discount (default 0), total, status (`pending`/`paid`/`failed`/`expired`/`refunded`), paid_at, expires_at | Indeks (user_id, status) |
| `order_items` | order_id, course_id, title_snapshot, price | Snapshot harga; siap untuk bundle di tahap 2 |
| `payments` | order_id, gateway, external_id (unik), payment_type, gross_amount, status, raw_payload (json), paid_at | Satu order bisa punya beberapa percobaan |
| `webhook_events` | gateway, event_id, payload (json), processed_at | Unik (gateway, event_id) untuk idempotensi |

### Sertifikat dan pengaturan
| Tabel | Kolom penting | Catatan / indeks |
|---|---|---|
| `certificates` | enrollment_id (unik), certificate_number (unik), verification_code (unik), pdf_path, issued_at | Satu sertifikat per enrollment |
| `settings` | key (unik), value (json) | Nama brand, logo, teks sertifikat, kontak |

## 7. Aturan bisnis penting, keamanan, dan ditunda ke tahap 2

**Aturan bisnis:**
- Enrollment dibuat hanya setelah order `paid` (berbayar) atau langsung (gratis). Tidak ada enrollment ganda untuk user dan kursus yang sama.
- Perubahan harga kursus tidak memengaruhi order lama (snapshot di `order_items`).
- Lesson yang dihapus setelah ada progres sebaiknya diarsipkan (status `draft`), bukan dihapus, agar riwayat tidak rusak.
- Kursus selesai jika semua lesson `published` yang wajib berstatus `completed` dan semua tugas wajib `approved`. Aturan pasti bisa disesuaikan dengan klien.
- Kedaluwarsa akses (`access_duration_days`) dihitung dari `enrolled_at`; null berarti seumur hidup.

**Keamanan:**
- Semua pengecekan akses dilakukan di server (Policy + `ComputeLessonAccess`), bukan hanya disembunyikan di UI.
- YouTube unlisted tidak bisa mencegah tautan dibagikan. Mitigasi: ID video hanya dikirim ke siswa yang berhak. Untuk proteksi lebih kuat, ganti ke Bunny/Vimeo lewat `VideoProvider`.
- Upload: batasi tipe dan ukuran, simpan di disk privat, nama file diacak, tampilkan lewat signed URL.
- Webhook pembayaran: verifikasi signature, idempoten, dan hanya percaya data dari webhook (jangan dari redirect browser).
- Rate limiting untuk login, daftar, dan unggah. Verifikasi email wajib sebelum beli.

**Ditunda ke tahap 2:** kuis/ujian, diskusi per lesson, kupon dan bundle, integrasi API Zoom/Meet, notifikasi WhatsApp, laporan lanjutan, review/rating kursus.

## 8. Roadmap implementasi dan risiko

| Fase | Isi | Hasil yang bisa didemokan |
|---|---|---|
| 0 | Setup proyek, starter kit React, CI, design token sementara, struktur folder | Aplikasi kosong yang bisa login |
| 1 | Auth, role/permission, katalog, halaman detail kursus | Katalog publik |
| 2 | Admin: CRUD kursus, modul, lesson (video, rangkuman, live) | Admin bisa menginput kursus |
| 3 | Order, Midtrans (sandbox), webhook, enrollment, kursus gratis | Beli dan dapat akses |
| 4 | Player lesson, drip + override instruktur, progres | Alur belajar lengkap |
| 5 | Tugas, submission, antrean instruktur, feedback tulisan + gambar | Siklus tugas selesai |
| 6 | Sertifikat PDF + halaman verifikasi | Kursus bisa diselesaikan |
| 7 | Notifikasi, laporan dasar, hardening, backup, deploy VPS | Siap diserahkan |

**Risiko utama:**
- **Aturan drip yang berbeda dari yang dibayangkan klien.** Konfirmasikan contoh nyata (kapan hari ke-1 dimulai, apa yang terjadi jika siswa tertinggal) sebelum fase 4.
- **Kebocoran video YouTube.** Sampaikan batasan ini ke klien sejak awal.
- **Webhook pembayaran.** Uji lengkap di sandbox (sukses, gagal, kedaluwarsa, webhook ganda).
- **Branding belum ada.** Pakai design token supaya penggantian tidak menyentuh komponen.
- **Scope creep.** Fitur tahap 2 baru dikerjakan setelah MVP diterima klien.

**Pertanyaan terbuka untuk klien:**
1. Syarat lulus sertifikat: semua lesson selesai saja, atau juga semua tugas wajib disetujui?
2. Apakah lesson berikutnya menunggu tugas disetujui, atau tetap terbuka sesuai jadwal?
3. Akses kursus seumur hidup atau ada batas waktu?
4. Apakah refund diperlukan, dan bagaimana akses dicabut saat refund?
