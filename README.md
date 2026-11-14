# Pixora LMS

LMS (Learning Management System) untuk menjual kursus online ke publik. Dibangun untuk **satu klien** (bukan multi-tenant).
Siswa mendaftar sendiri, membeli atau mengambil kursus gratis, belajar lewat video + rangkuman teks dengan jadwal drip,
mengumpulkan tugas, dan mendapat sertifikat otomatis.

Rancangan lengkap (arsitektur, ERD, aturan bisnis, roadmap): [docs/arsitektur-erd.md](docs/arsitektur-erd.md).

> Status: **Fase 4** selesai — batch (angkatan) per kursus drip, pilihan batch saat checkout, player lesson
> (`/belajar`), drip per batch dengan override instruktur per batch/siswa (`/kelas`), dan progres belajar.
> Fase 3 (checkout, Midtrans Snap sandbox, webhook idempoten, enrollment, pesanan) sudah selesai.
> Berikutnya: Fase 5 (tugas, submission, feedback).

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
php artisan storage:link   # URL publik untuk gambar sampul kursus

# 6. Jalankan aplikasi (server PHP, queue worker, log, dan Vite sekaligus)
composer dev
```

Buka http://localhost:8000.

### Halaman dan akun dev

| Halaman              | URL                            | Akses                                    |
| -------------------- | ------------------------------ | ---------------------------------------- |
| Beranda              | `/`                            | Publik                                   |
| Katalog kursus       | `/kursus`                      | Publik (cari, filter kategori & harga)   |
| Detail kursus        | `/kursus/{slug}`               | Publik, hanya kursus berstatus terbit    |
| Dasbor (sesuai role) | `/dashboard` → redirect        | Login + email terverifikasi              |
| Dasbor admin/staf    | `/admin`                       | Role `admin` atau `staff`                |
| Dasbor instruktur    | `/instruktur`                  | Role `instructor`                        |
| Dasbor siswa         | `/siswa`                       | Role `student` (otomatis saat daftar)    |
| Kelola kursus        | `/admin/kursus`                | Permission `courses.manage` (admin)      |
| Kurikulum kursus     | `/admin/kursus/{id}/kurikulum` | Permission `courses.manage` (admin)      |
| Pengguna             | `/admin/pengguna`              | Permission `users.manage` (admin)        |
| Kursus saya          | `/instruktur/kursus`           | Role `instructor`, hanya kursus miliknya |
| Checkout             | `/kursus/{slug}/checkout`      | Siswa (login + email terverifikasi)      |
| Kursus saya (siswa)  | `/siswa/kursus`                | Role `student`                           |
| Pesanan & invoice    | `/siswa/pesanan`               | Role `student`, hanya miliknya           |
| Pesanan (admin)      | `/admin/pesanan`               | Permission `orders.manage` (admin, staf) |

Akun dev dari `php artisan migrate --seed` (hanya `local`/`testing`, emailnya sudah terverifikasi):

| Role       | Email                    | Kata sandi                                           |
| ---------- | ------------------------ | ---------------------------------------------------- |
| admin      | `admin@pixora.test`      | `SEED_ADMIN_PASSWORD` di `.env` (default `password`) |
| staff      | `staff@pixora.test`      | `password`                                           |
| instructor | `instructor@pixora.test` | `password`                                           |
| student    | `student@pixora.test`    | `password`                                           |
| student    | `siswa1..8@pixora.test`  | `password` (siswa demo di batch kursus drip)         |

Email admin bisa diubah lewat `SEED_ADMIN_EMAIL`. Di production, jalankan hanya `php artisan db:seed --class=RoleSeeder --force`.

Catatan:

- Port MySQL host default **3306**. Jika port itu sudah dipakai (mis. MySQL lain), ubah `FORWARD_DB_PORT` dan `DB_PORT` di `.env` (contoh: 3307).
- Kredensial di `.env.example` dan `docker-compose.yml` hanya untuk pengembangan lokal.
- Locale aplikasi `id`, zona waktu `Asia/Jakarta`.
- Email dikirim ke log (`MAIL_MAILER=log`) di lokal: cek `storage/logs/laravel.log`. Email dikirim lewat queue
  (`QUEUE_CONNECTION=redis`), jadi worker harus jalan (`composer dev` sudah menjalankan `queue:listen`).
- Pembayaran lokal default memakai **gateway simulasi** (`PAYMENT_GATEWAY=fake`): di halaman pesanan muncul tombol
  "Bayar berhasil / Pending / Ditolak / Kedaluwarsa" yang mengirim notifikasi bertanda tangan ke webhook — tanpa key.
- Data demo Fase 4 dibuat relatif terhadap hari ini: setiap kursus drip punya 3 batch (selesai, berjalan, akan datang);
  `student@pixora.test` ikut batch berjalan "Dasar Desain Grafis untuk Pemula" (dengan 1 override batch + 1 override
  khusus siswa) dan kursus gratis non-drip. Instruktur `instructor@pixora.test` mengelola kelasnya di **Kursus saya →
  Kelola kelas**.
- Jam buka lesson drip: `LESSON_UNLOCK_TIME` (default `00:00`, zona `Asia/Jakarta`); offset 0 = tanggal mulai batch.
- Scheduler (order & enrollment kedaluwarsa): di server tambahkan cron `* * * * * php artisan schedule:run`;
  di lokal jalankan `php artisan schedule:work` bila perlu.

## Setup Midtrans sandbox

1. **Akun & key.** Daftar di [dashboard.sandbox.midtrans.com](https://dashboard.sandbox.midtrans.com), buka
   _Settings → Access Keys_, lalu isi `.env` (jangan pernah commit key):

    ```dotenv
    PAYMENT_GATEWAY=midtrans
    MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxx
    MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxx
    MIDTRANS_IS_PRODUCTION=false
    ```

    Lalu `php artisan config:clear`.

2. **Tunnel lokal.** Midtrans harus bisa menjangkau webhook Anda. Jalankan aplikasi (`composer dev`) lalu buka tunnel
   ke port 8000, misalnya `cloudflared tunnel --url http://localhost:8000` atau `ngrok http 8000`. Catat URL HTTPS-nya.
   Set juga `APP_URL` ke URL tunnel agar URL callback/email benar.

3. **Payment Notification URL.** Di dashboard sandbox: _Settings → Payment (Configuration)_ → isi
   **Payment Notification URL** = `https://<url-tunnel>/webhooks/midtrans`. (Finish/Unfinish/Error redirect URL boleh
   dikosongkan — aplikasi mengirim URL kembali per transaksi dan **tidak** mempercayai redirect sebagai bukti bayar.)

4. **Tes pembayaran.**
    - Login sebagai `student@pixora.test`, buka kursus berbayar → **Beli** → **Lanjut ke pembayaran** → pop-up Snap.
    - Pilih metode, mis. _BCA Virtual Account_, salin nomor VA, lalu bayar lewat
      [simulator.sandbox.midtrans.com](https://simulator.sandbox.midtrans.com) (menu bank terkait).
    - Kartu kredit uji: `4811 1111 1111 1114`, CVV `123`, kedaluwarsa bulan/tahun di masa depan, OTP `112233`.
    - Dalam beberapa detik webhook masuk: status pesanan **Lunas**, kursus muncul di **Kursus saya**, email terkirim.
    - Jika webhook terlambat, tombol **Cek status pembayaran** (siswa) atau **Cek status ke gateway** (admin) mengambil
      status dari Status API Midtrans.

5. **Mengecek webhook.** Riwayat ada di tabel `webhook_events` dan `payments`; penolakan (signature salah / nominal
   tidak cocok) tercatat sebagai warning di `storage/logs/laravel.log` (hanya nomor order, tanpa payload/key).

## Perintah umum

| Perintah                               | Keterangan                                                                               |
| -------------------------------------- | ---------------------------------------------------------------------------------------- |
| `composer dev`                         | Jalankan server, queue, log (pail), dan Vite                                             |
| `php artisan test`                     | Jalankan test (Pest, SQLite in-memory)                                                   |
| `vendor/bin/pint`                      | Format kode PHP (`--test` untuk cek saja)                                                |
| `vendor/bin/phpstan analyse`           | Analisis statis PHP (Larastan)                                                           |
| `npm run lint`                         | Cek lint + format frontend (`npm run lint:fix` untuk fix)                                |
| `npm run types:check`                  | Type-check TypeScript                                                                    |
| `npm run build`                        | Build aset produksi                                                                      |
| `php artisan migrate`                  | Jalankan migrasi database                                                                |
| `php artisan migrate:fresh --seed`     | Reset database + data awal (local)                                                       |
| `docker compose up -d`                 | Nyalakan MySQL + Redis                                                                   |
| `docker compose down`                  | Matikan container (tambah `-v` untuk hapus data)                                         |
| `php artisan orders:expire-pending`    | Tandai order pending yang lewat batas waktu sebagai kedaluwarsa (terjadwal tiap 5 menit) |
| `php artisan enrollments:expire-ended` | Tandai enrollment yang lewat masa akses sebagai kedaluwarsa (terjadwal harian)           |
| `php artisan schedule:work`            | Jalankan scheduler di lokal                                                              |

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
