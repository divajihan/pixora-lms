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
- Fase saat ini: **Fase 0 — setup** (selesai di branch `chore/phase-0-setup`). Fase berikutnya: Fase 1 (auth, role/permission, katalog).

## Stack

- Laravel 13, PHP 8.3+ (Composer `config.platform.php` dipatok 8.3 agar lock file kompatibel dengan CI)
- Inertia + React 19 + TypeScript + shadcn/ui + Tailwind CSS 4 (starter kit resmi Laravel), Wayfinder untuk route TS
- MySQL 8 (Docker, port host 3307), Redis untuk cache/queue/session
- Fortify (auth), spatie/laravel-permission (role), Pest 4 (test)
- Tooling frontend: Vite+ (`vp`) — oxlint + oxfmt, bukan ESLint/Prettier

## Struktur folder

```
app/
  Actions/<Modul>/      # satu kelas = satu use case (Enrollment, Payment, Learning, Assignment, Certificate)
  Actions/Fortify/      # action bawaan starter kit (registrasi, reset password)
  Enums/                # backed enum string untuk semua kolom status/type
  Http/Controllers/     # Public/, Student/, Instructor/, Admin/, Webhook/ — controller tipis
  Models/
  Policies/             # CoursePolicy, LessonPolicy, SubmissionPolicy, OrderPolicy
  Services/Payment/     # PaymentGateway (interface) + MidtransGateway
  Services/Video/       # VideoProvider (interface) + YouTubeProvider
resources/css/app.css   # design token (blok --brand-*), pola variabel shadcn/ui
resources/js/pages/     # halaman Inertia
tests/Feature, tests/Unit
docs/                   # rancangan (jangan diformat ulang)
```

## Konvensi

- **Bahasa:** UI dalam Bahasa Indonesia. Kode, nama tabel/kolom, nama kelas, komentar kode, dan pesan commit dalam bahasa Inggris.
- **Controller tipis:** validasi (Form Request), panggil Action, kembalikan response Inertia/redirect. Tidak ada query bisnis di controller.
- **Logika bisnis di Actions:** satu kelas satu tugas, satu method publik (`handle()`), dependency via constructor. Operasi multi-tabel dibungkus `DB::transaction`.
- **Otorisasi lewat Policy** (+ role/permission spatie). Semua cek akses di server; menyembunyikan UI bukan otorisasi.
- **Enum untuk status:** kolom `status`/`type` bertipe string di DB, di-cast ke backed enum PHP di model. Nilai sesuai kamus tabel di docs.
- **Harga = integer rupiah** (tanpa desimal, tanpa float). Contoh: `150000`. Format tampilan di frontend dengan `Intl.NumberFormat('id-ID')`.
- **Snapshot harga** di `order_items`; webhook pembayaran harus diverifikasi signature dan idempoten.
- **Payment & video** hanya diakses lewat interface `PaymentGateway` / `VideoProvider`, jangan panggil SDK vendor langsung dari Action/controller.
- **Design token:** jangan hard-code warna; pakai token semantik (`bg-primary`, `text-muted-foreground`, ...). Nilai brand sementara ada di blok `--brand-*`.
- **Waktu:** zona waktu aplikasi `Asia/Jakarta`, locale `id`.
- **Rahasia:** jangan pernah commit `.env` atau kredensial; tambahkan key baru ke `.env.example` dengan nilai kosong/dummy.
- Setiap fitur baru disertai test Pest (feature test untuk alur HTTP, unit test untuk Action yang kompleks).

## Perintah

```bash
docker compose up -d --wait     # MySQL + Redis
composer dev                    # server + queue + pail + vite

php artisan test                # Pest (SQLite in-memory, lihat phpunit.xml)
vendor/bin/pint                 # format PHP   (--test untuk cek saja)
vendor/bin/phpstan analyse      # Larastan
npm run lint                    # oxlint + oxfmt (npm run lint:fix untuk memperbaiki)
npm run types:check             # tsc --noEmit
npm run build                   # build aset produksi
php artisan migrate             # migrasi ke MySQL lokal
```

Sebelum commit/PR pastikan semua hijau: `vendor/bin/pint --test`, `npm run lint`, `npm run types:check`,
`vendor/bin/phpstan analyse`, `npm run build`, `php artisan test`. CI (`.github/workflows/ci.yml`) menjalankan hal yang sama.

## Git

- Kerjakan di branch per fase/fitur (`chore/...`, `feat/...`), commit kecil dengan pesan jelas, PR ke `main`.
