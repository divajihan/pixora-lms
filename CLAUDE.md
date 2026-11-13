# CLAUDE.md — Pixora LMS

## Ringkasan proyek

LMS kursus online untuk **satu klien** (bukan multi-tenant) yang menjual kursus ke publik: katalog, pembelian (Midtrans),
kursus gratis, player lesson dengan drip, tugas + feedback instruktur, dan sertifikat PDF.

Acuan utama: [`docs/arsitektur-erd.md`](docs/arsitektur-erd.md) (arsitektur, ERD, kamus tabel, aturan bisnis, roadmap fase).
Baca dokumen itu sebelum mengerjakan fitur apa pun.

## Aturan fase (WAJIB)

- Proyek dikerjakan per fase sesuai roadmap di `docs/arsitektur-erd.md` bagian 8.
- **Jangan mengimplementasikan fitur, tabel, model, route, atau seed di luar fase yang sedang dikerjakan.**
  Jika butuh sesuatu dari fase berikutnya, catat sebagai TODO/asumsi, jangan dibuat.
- Fitur "ditunda ke tahap 2" (kuis, diskusi, kupon, bundle, API Zoom/Meet, WhatsApp, rating) tidak dikerjakan sebelum MVP diterima.
- Fase selesai: **Fase 0** (setup), **Fase 1** (auth, role/permission, katalog publik — branch `feat/phase-1-auth-catalog`).
  Fase berikutnya: **Fase 2** (admin: CRUD kursus, modul, lesson).

## Stack

- Laravel 13, PHP 8.3+ (Composer `config.platform.php` dipatok 8.3 agar lock file kompatibel dengan CI)
- Inertia 3 + React 19 + TypeScript + shadcn/ui + Tailwind CSS 4 (starter kit resmi Laravel), Wayfinder untuk route TS
- MySQL 8 (Docker, port host 3306), Redis untuk cache/queue/session
- Fortify (auth + verifikasi email + 2FA + passkey), spatie/laravel-permission (role), Pest 4 (test)
- Tooling frontend: Vite+ (`vp`) — oxlint + oxfmt, bukan ESLint/Prettier

## Struktur folder

```
app/
  Actions/Catalog/      # SearchCourses (+ CatalogFilters) — query katalog publik
  Actions/Fortify/      # action starter kit (CreateNewUser memberi role student)
  Actions/<Modul>/      # fase berikutnya: Enrollment, Payment, Learning, Assignment, Certificate
  Enums/                # Role, Permission, CourseStatus, CourseInstructorRole, CoursePriceFilter
  Http/Controllers/
    Public/             # HomeController, CourseController (katalog & detail)
    DashboardController # /dashboard → redirect ke dasbor sesuai role
    Settings/           # bawaan starter kit
  Http/Middleware/      # EnsureUserIsActive (logout user nonaktif), HandleInertiaRequests
  Http/Resources/       # CourseCardResource, CourseDetailResource (bentuk data untuk Inertia)
  Models/               # User, Category, Course, CourseInstructor (pivot), InstructorProfile
  Policies/             # CoursePolicy (fase berikutnya: Lesson, Submission, Order)
  Services/Payment/     # PaymentGateway (interface) + MidtransGateway
  Services/Video/       # VideoProvider (interface) + YouTubeProvider
config/pixora.php       # konfigurasi aplikasi (akun seed dev)
database/seeders/       # RoleSeeder (semua env), DevelopmentSeeder + CatalogSeeder (local/testing saja)
lang/id/, lang/id.json  # terjemahan auth, validasi, password, email
resources/css/app.css   # design token (blok --brand-*), pola variabel shadcn/ui
resources/js/
  pages/public/         # home, courses/index, courses/show (layout: layouts/public-layout.tsx)
  pages/{admin,instructor,student}/dashboard.tsx   # placeholder dasbor per role
  components/catalog/   # course-card, course-price, course-thumbnail, pagination
  components/seo-head.tsx   # title + meta description per halaman
  lib/format.ts         # formatRupiah, formatDate
  lib/roles.ts          # dashboardFor(roles), label role
tests/Feature, tests/Unit
docs/                   # rancangan (jangan diformat ulang)
```

## Role & permission

Role dan permission didefinisikan di enum `App\Enums\Role` dan `App\Enums\Permission`, lalu disinkronkan oleh
`RoleSeeder` (idempoten, aman di production). Tambah permission baru di enum, lalu jalankan ulang seeder.

| Permission           | Admin | Staf | Instruktur | Siswa | Keterangan                                        |
| -------------------- | :---: | :--: | :--------: | :---: | ------------------------------------------------- |
| `courses.manage`     |   ✓   |      |            |       | CRUD kursus, modul, lesson (Fase 2)               |
| `courses.teach`      |   ✓   |      |     ✓      |       | Kelola kursus yang ditugaskan: unlock, siswa (F4) |
| `courses.enroll`     |   ✓   |      |            |   ✓   | Beli/ambil kursus (Fase 3)                        |
| `assignments.review` |   ✓   |      |     ✓      |       | Periksa tugas dan beri feedback (Fase 5)          |
| `orders.manage`      |   ✓   |  ✓   |            |       | Kelola order (Fase 3)                             |
| `payments.confirm`   |   ✓   |  ✓   |            |       | Konfirmasi pembayaran manual (Fase 3)             |
| `users.manage`       |   ✓   |      |            |       | Kelola pengguna dan role                          |
| `users.view`         |   ✓   |  ✓   |            |       | Lihat data pengguna                               |
| `students.assist`    |   ✓   |  ✓   |            |       | Bantu siswa (lihat enrollment/order mereka)       |
| `content.moderate`   |   ✓   |  ✓   |            |       | Moderasi konten                                   |
| `reports.view`       |   ✓   |      |            |       | Laporan                                           |
| `settings.manage`    |   ✓   |      |            |       | Pengaturan (branding, sertifikat)                 |

- Aturan "hanya kursus miliknya" (instruktur) dicek di Policy memakai `course_instructors`, di atas permission.
- Area per role dilindungi middleware `role:` di `routes/web.php`: `/admin` (admin, staff), `/instruktur`, `/siswa`.
  `/dashboard` (home Fortify) mengarahkan ke dasbor sesuai role tertinggi (`User::primaryRole()`).
- Registrasi publik selalu memberi role `student` (`CreateNewUser`). User `is_active = false` tidak bisa login
  (`FortifyServiceProvider::configureAuthentication`) dan sesi aktifnya diakhiri (`EnsureUserIsActive`).

## Konvensi

- **Bahasa:** UI dalam Bahasa Indonesia. Kode, nama tabel/kolom, nama kelas, komentar kode, dan pesan commit dalam bahasa Inggris.
  URL publik berbahasa Indonesia (`/kursus`, `?kategori=`, `?harga=gratis|berbayar`); nama route tetap Inggris (`courses.index`).
- **Controller tipis:** validasi (Form Request), panggil Action, kembalikan response Inertia/redirect. Query lebih dari trivial ada di Action.
- **Logika bisnis di Actions:** satu kelas satu tugas, satu method publik (`handle()`), dependency via constructor. Operasi multi-tabel dibungkus `DB::transaction`.
- **Otorisasi lewat Policy** (+ role/permission spatie). Semua cek akses di server; menyembunyikan UI bukan otorisasi.
  Resource yang tidak boleh terlihat publik ditolak sebagai 404 (`Response::denyAsNotFound()`), bukan 403.
- **Enum untuk status:** kolom `status`/`type` bertipe string di DB, di-cast ke backed enum PHP di model. Nilai sesuai kamus tabel di docs.
- **Harga = integer rupiah** (tanpa desimal, tanpa float). Contoh: `150000`. Tampilkan dengan `formatRupiah()` (`resources/js/lib/format.ts`).
  Kursus gratis: `is_free = true` dan `price = 0`.
- **Data ke frontend lewat JSON Resource** (`app/Http/Resources`); jangan kirim model mentah untuk data publik.
- **Eager loading:** listing memakai scope seperti `Course::withCardRelations()`; ada test yang memastikan jumlah query tidak bertambah.
- **SEO:** setiap halaman publik mengirim prop `meta` (`title`, `description`) dan memakai `<SeoHead>`; Blade merender tag yang sama di server.
- **Snapshot harga** di `order_items`; webhook pembayaran harus diverifikasi signature dan idempoten.
- **Payment & video** hanya diakses lewat interface `PaymentGateway` / `VideoProvider`, jangan panggil SDK vendor langsung dari Action/controller.
- **Design token:** jangan hard-code warna; pakai token semantik (`bg-primary`, `text-muted-foreground`, ...). Nilai brand sementara ada di blok `--brand-*`.
- **Waktu:** zona waktu aplikasi `Asia/Jakarta`, locale `id`.
- **Rahasia:** jangan pernah commit `.env` atau kredensial; tambahkan key baru ke `.env.example` dengan nilai kosong/dummy.
- Setiap fitur baru disertai test Pest (feature test untuk alur HTTP, unit test untuk Action/Policy). Role & permission
  di-seed otomatis untuk test yang memakai `RefreshDatabase` (`Tests\TestCase::$seeder`).

## Perintah

```bash
docker compose up -d --wait     # MySQL + Redis
composer dev                    # server + queue + pail + vite

php artisan migrate:fresh --seed   # reset DB + role + akun dev + katalog demo (local saja)
php artisan db:seed --class=RoleSeeder --force   # production: hanya role & permission

php artisan test                # Pest (SQLite in-memory, lihat phpunit.xml)
vendor/bin/pint                 # format PHP   (--test untuk cek saja)
vendor/bin/phpstan analyse      # Larastan
npm run lint                    # oxlint + oxfmt (npm run lint:fix untuk memperbaiki)
npm run types:check             # tsc --noEmit (needs Wayfinder files: run `php artisan wayfinder:generate --with-form`, `npm run dev`, or `npm run build` first)
npm run build                   # build aset produksi
php artisan migrate             # migrasi ke MySQL lokal
```

Akun dev (dibuat `DevelopmentSeeder`, hanya di `local`/`testing`): admin dari `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD`
(default `admin@pixora.test` / `password`), lalu `staff@pixora.test`, `instructor@pixora.test`, `student@pixora.test`
dengan kata sandi `password`. Seeder ini menolak berjalan di production.

Sebelum commit/PR pastikan semua hijau: `vendor/bin/pint --test`, `npm run lint`, `npm run types:check`,
`vendor/bin/phpstan analyse`, `npm run build`, `php artisan test`. CI (`.github/workflows/ci.yml`) menjalankan hal yang sama.

## Git

- Kerjakan di branch per fase/fitur (`chore/...`, `feat/...`), commit kecil dengan pesan jelas, PR ke `main`.
