# Pixora LMS

LMS (Learning Management System) untuk menjual kursus online ke publik. Dibangun untuk **satu klien** (bukan multi-tenant).
Siswa mendaftar sendiri, membeli atau mengambil kursus gratis, belajar lewat video + rangkuman teks dengan jadwal drip,
mengumpulkan tugas, dan mendapat sertifikat otomatis.

Rancangan lengkap (arsitektur, ERD, aturan bisnis, roadmap): [docs/arsitektur-erd.md](docs/arsitektur-erd.md).

> Status: **Fase 1** selesai — autentikasi (verifikasi email, akun nonaktif), role & permission, dasbor per role
> (placeholder), dan katalog kursus publik. Berikutnya: Fase 2 (admin mengelola kursus, modul, lesson).

## Stack

| Lapisan               | Pilihan                                                                   |
| --------------------- | ------------------------------------------------------------------------- |
| Backend               | Laravel 13, PHP 8.3+                                                      |
| Frontend              | Inertia + React 19 + shadcn/ui + Tailwind CSS 4 (starter kit resmi)       |
| Database              | MySQL 8                                                                   |
| Cache, queue, session | Redis                                                                     |
| Auth                  | Laravel Fortify (dari starter kit)                                        |
| Role/permission       | spatie/laravel-permission                                                 |
| Test                  | Pest                                                                      |
| Lint                  | Pint (PHP), Vite+ `vp check` = oxlint + oxfmt (frontend), Larastan, `tsc` |

## Prasyarat

- PHP **8.3+** dengan ekstensi `pdo_mysql`, `redis` (phpredis), `mbstring`, `intl`, `bcmath`, `pdo_sqlite` (untuk test)
- Composer 2
- Node.js **22.18+** (lihat `.nvmrc`)
- Docker + Docker Compose (untuk MySQL dan Redis lokal)

> Windows: cara termudah adalah [Laravel Herd](https://herd.laravel.com) atau `php.new`, yang sudah menyertakan PHP, Composer, dan ekstensi di atas.

## Setup lokal

```bash
# 1. Clone dan masuk ke folder proyek
git clone https://github.com/divajihan/pixora-lms.git
cd pixora-lms

# 2. Salin konfigurasi environment, lalu sesuaikan bila perlu
cp .env.example .env

# 3. Jalankan MySQL 8 dan Redis (MySQL di port host 3306, Redis di 6379)
docker compose up -d --wait

# 4. Install dependency
composer install
npm ci

# 5. Buat application key, jalankan migrasi, dan isi data awal
#    (role & permission, akun dev per role, katalog demo — hanya di local)
php artisan key:generate
php artisan migrate --seed

# 6. Jalankan aplikasi (server PHP, queue worker, log, dan Vite sekaligus)
composer dev
```

Buka http://localhost:8000.

### Halaman dan akun dev

| Halaman              | URL                     | Akses                                  |
| -------------------- | ----------------------- | -------------------------------------- |
| Beranda              | `/`                     | Publik                                 |
| Katalog kursus       | `/kursus`               | Publik (cari, filter kategori & harga) |
| Detail kursus        | `/kursus/{slug}`        | Publik, hanya kursus berstatus terbit  |
| Dasbor (sesuai role) | `/dashboard` → redirect | Login + email terverifikasi            |
| Dasbor admin/staf    | `/admin`                | Role `admin` atau `staff`              |
| Dasbor instruktur    | `/instruktur`           | Role `instructor`                      |
| Dasbor siswa         | `/siswa`                | Role `student` (otomatis saat daftar)  |

Akun dev dari `php artisan migrate --seed` (hanya `local`/`testing`, emailnya sudah terverifikasi):

| Role       | Email                    | Kata sandi                                           |
| ---------- | ------------------------ | ---------------------------------------------------- |
| admin      | `admin@pixora.test`      | `SEED_ADMIN_PASSWORD` di `.env` (default `password`) |
| staff      | `staff@pixora.test`      | `password`                                           |
| instructor | `instructor@pixora.test` | `password`                                           |
| student    | `student@pixora.test`    | `password`                                           |

Email admin bisa diubah lewat `SEED_ADMIN_EMAIL`. Di production, jalankan hanya `php artisan db:seed --class=RoleSeeder --force`.

Catatan:

- Port MySQL host default **3306**. Jika port itu sudah dipakai (mis. MySQL lain), ubah `FORWARD_DB_PORT` dan `DB_PORT` di `.env` (contoh: 3307).
- Kredensial di `.env.example` dan `docker-compose.yml` hanya untuk pengembangan lokal.
- Locale aplikasi `id`, zona waktu `Asia/Jakarta`.
- Email dikirim ke log (`MAIL_MAILER=log`) di lokal: cek `storage/logs/laravel.log`.

## Perintah umum

| Perintah                           | Keterangan                                                |
| ---------------------------------- | --------------------------------------------------------- |
| `composer dev`                     | Jalankan server, queue, log (pail), dan Vite              |
| `php artisan test`                 | Jalankan test (Pest, SQLite in-memory)                    |
| `vendor/bin/pint`                  | Format kode PHP (`--test` untuk cek saja)                 |
| `vendor/bin/phpstan analyse`       | Analisis statis PHP (Larastan)                            |
| `npm run lint`                     | Cek lint + format frontend (`npm run lint:fix` untuk fix) |
| `npm run types:check`              | Type-check TypeScript                                     |
| `npm run build`                    | Build aset produksi                                       |
| `php artisan migrate`              | Jalankan migrasi database                                 |
| `php artisan migrate:fresh --seed` | Reset database + data awal (local)                        |
| `docker compose up -d`             | Nyalakan MySQL + Redis                                    |
| `docker compose down`              | Matikan container (tambah `-v` untuk hapus data)          |

## Struktur folder (ringkas)

```
app/
  Actions/            # satu kelas = satu use case (logika bisnis)
  Enums/              # enum status (CourseStatus, OrderStatus, ...)
  Http/Controllers/   # controller tipis
  Models/
  Policies/           # otorisasi
  Services/
    Payment/          # PaymentGateway (interface) + implementasi Midtrans (Fase 3)
    Video/            # VideoProvider (interface) + implementasi YouTube
resources/
  css/app.css         # design token (CSS variables, pola shadcn/ui)
  js/pages/           # halaman Inertia (React)
docs/                 # rancangan arsitektur & ERD
```

## Branding / design token

Branding klien belum tersedia. Token sementara (warna primer, skala netral, radius, font) didefinisikan di
`resources/css/app.css` (blok `--brand-*`) dan font di `vite.config.ts`. Komponen hanya memakai token semantik shadcn/ui
(`bg-primary`, `text-muted-foreground`, ...) sehingga penggantian brand cukup mengubah blok tersebut.

## CI

GitHub Actions (`.github/workflows/ci.yml`) berjalan pada push ke `main` dan setiap pull request: Pint, `npm run lint`,
`tsc`, Larastan, migrasi ke MySQL 8, `npm run build`, dan `php artisan test`.
