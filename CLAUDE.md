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
- Fase selesai: **Fase 0** (setup), **Fase 1** (auth, role/permission, katalog publik),
  **Fase 2** (panel admin konten: kursus, kurikulum, pengguna; instruktur read-only),
  **Fase 3** (checkout, Midtrans Snap sandbox, webhook, enrollment, kursus gratis, pesanan — branch `feat/phase-3-checkout-enrollment`).
  **Fase 4** (batch per kursus drip, player lesson, drip per batch + override instruktur, progres — branch
  `feat/phase-4-batches-learning`).
  **Fase 5** (tugas gambar, tenggat + perpanjangan, pengumpulan, feedback instruktur/staf — branch `feat/phase-5-assignments`).
  Fase berikutnya: **Fase 6** (sertifikat). Jangan membuat sertifikat, notifikasi email, atau laporan sebelum fasenya.

## Stack

- Laravel 13, PHP 8.3+ (Composer `config.platform.php` dipatok 8.3 agar lock file kompatibel dengan CI)
- Inertia 3 + React 19 + TypeScript + shadcn/ui + Tailwind CSS 4 (starter kit resmi Laravel), Wayfinder untuk route TS
- MySQL 8 (Docker, port host 3306), Redis untuk cache/queue/session
- Fortify (auth + verifikasi email + 2FA + passkey), spatie/laravel-permission (role), Pest 4 (test)
- Tooling frontend: Vite+ (`vp`) — oxlint + oxfmt, bukan ESLint/Prettier

## Struktur folder

```
app/
  Actions/Catalog/      # SearchCourses (+ CatalogFilters), PublicCurriculum (kurikulum publik, aman)
  Actions/Courses/      # SaveCourse, PublishCourse, ChangeCourseStatus, AssignInstructor, ListAdminCourses
  Actions/Curriculum/   # SaveLesson, ReorderCurriculum, DeleteModule, LoadCurriculum
  Actions/Users/        # CreateStaffAccount (kata sandi sementara), UpdateUserAccount (guard admin terakhir)
  Actions/Fortify/      # action starter kit (CreateNewUser memberi role student)
  Actions/Orders/       # CreateOrder, StartPayment (Snap token), MarkOrderPaid, RefundOrder (admin, catatan wajib)
  Actions/Payments/     # HandlePaymentWebhook, ApplyPaymentNotification (inti idempoten), ReconcileOrder (Status API)
  Actions/Enrollments/  # GrantEnrollment (race-safe), EnrollInFreeCourse
  Actions/Catalog/PurchaseState.php   # CTA halaman kursus (beli / gratis / lanjut belajar / lanjut bayar)
  Actions/Learning/     # ComputeLessonAccess (+ LessonAccess), BuildCourseOutline, SetLessonUnlockOverride,
                        # MoveEnrollmentToBatch, MarkLessonCompleted, UnmarkLessonCompleted, RecalculateEnrollmentProgress
  Actions/Batches/      # SaveBatch (simpan + hapus dengan aturan)
  Actions/Enrollments/ResolveEnrollmentBatch.php   # validasi batch pilihan saat checkout/ambil gratis
  Actions/Assignments/  # ComputeAssignmentDeadline (+ AssignmentDeadline), AssignmentAvailability, SubmissionRules,
                        # SubmitAssignment, UpdateSubmission, PostSubmissionComment, EditSubmissionComment,
                        # SetDeadlineOverride, SaveAssignment, StoreImages, BuildAssignmentPanel,
                        # BuildAssignmentReview, ListStudentAssignments, ListSubmissionsToReview
  Actions/<Modul>/      # fase berikutnya: Certificate
  Enums/                # Role, Permission, CourseStatus, CourseInstructorRole, CoursePriceFilter, LessonType, LessonStatus
  Http/Controllers/
    Public/             # HomeController, CourseController (katalog & detail + kurikulum publik)
    Admin/              # CourseController, CourseStatusController, CourseInstructorController,
                        # CurriculumController, ModuleController, LessonController, UserController
    Instructor/         # CourseController ("Kursus saya", read-only)
    Student/            # CourseController (kursus saya), OrderController (pesanan, invoice, cek status, simulasi),
                        # AssignmentController ("Tugas saya", proyek akhir, kumpul/ubah)
    Review/             # AssignmentReviewController (/kelas/{course}/tugas, perpanjangan tenggat),
                        # SubmissionReviewController (/pengumpulan, detail, komentar)
    Admin/AssignmentController   # tugas di kurikulum (courses.manage)
    AttachmentController         # satu-satunya jalur baca gambar tugas (Policy)
    Instructor/DashboardController   # kartu "Perlu direview"
    LearnController     # player /belajar/{course}[/lesson/{lesson}] + tandai/batalkan selesai
    ClassController     # /kelas/{course}/batch/...: siswa, jadwal batch & per siswa, pindah batch (admin)
    Admin/BatchController   # batch per kursus drip (courses.manage)
    Admin/OrderController   # pesanan (orders.manage); tandai lunas/refund (admin)
    CheckoutController  # ringkasan, buat order, ambil gratis
    WebhookController   # POST /webhooks/midtrans
    DashboardController # /dashboard → redirect ke dasbor sesuai role
    Settings/           # bawaan starter kit
  Http/Middleware/      # EnsureUserIsActive (logout user nonaktif), HandleInertiaRequests
  Http/Requests/Admin/  # CourseRequest, LessonRequest, ModuleRequest, ReorderCurriculumRequest, *UserRequest, ...
  Http/Resources/       # CourseCard/CourseDetail (publik), Admin/AdminCourseResource, Admin/CurriculumLessonResource (staf)
  Models/               # User, Category, Course, CourseInstructor (pivot), InstructorProfile, Module, Lesson, LiveSession,
                        # Batch, LessonUnlockOverride, LessonProgress, Assignment, Submission, SubmissionComment,
                        # Attachment (morph map: submission, submission_comment), AssignmentDeadlineOverride
  Notifications/        # PaymentSucceeded (email, queued)
  Policies/             # CoursePolicy (+ manageClass), ModulePolicy, LessonPolicy (+ viewContent/complete), OrderPolicy,
                        # EnrollmentPolicy, AssignmentPolicy, SubmissionPolicy, SubmissionCommentPolicy, AttachmentPolicy
  Services/Images/      # ImageNormalizer (GD: buang metadata, perkecil, thumbnail)
  Rules/AllowedImage.php   # tipe gambar dari isi file (finfo): JPEG/PNG/WebP
  Jobs/DeleteStoredFiles.php   # hapus file lampiran setelah commit
  Jobs/RecalculateCourseProgress.php   # hitung ulang progres saat lesson terbit/ditarik/dihapus (LessonObserver)
  Rules/VideoUrl.php    # validasi URL video lewat VideoProvider
  Services/Payment/     # PaymentGateway (interface), MidtransGateway (HTTP client), FakeGateway (lokal/test), MidtransPayload
  Services/Video/       # VideoProvider (interface) + YouTubeProvider (di-bind di AppServiceProvider)
  Support/              # HtmlSanitizer (purify "rich_text"), UniqueSlug
config/pixora.php       # konfigurasi aplikasi (akun seed dev)
config/purify.php       # allowlist HTML; profil "rich_text" untuk ringkasan lesson
database/seeders/       # RoleSeeder (semua env), DevelopmentSeeder + CatalogSeeder (local/testing saja)
lang/id/, lang/id.json  # terjemahan auth, validasi, password, email
resources/css/app.css   # design token (blok --brand-*), pola variabel shadcn/ui, gaya .rich-text
resources/js/
  pages/public/         # home, courses/index, courses/show (layout: layouts/public-layout.tsx)
  pages/admin/          # dashboard, courses/{index,create,edit,curriculum}, users/index
  pages/instructor/     # dashboard, courses/{index,show}
  pages/student/        # dashboard, courses/index, orders/{index,show,invoice}, assignments/index ("Tugas saya")
  pages/learn/final-project.tsx   # proyek akhir (blok tugas)
  pages/classes/        # + assignments, assignment-students (review per batch)
  pages/submissions/    # index (staf/admin), show (detail + komentar)
  components/assignments/   # assignment-panel (siswa), image-picker, image-gallery (lightbox), comment-thread,
                            # deadline-dialog, batch-select
  components/admin/curriculum/assignment-dialog.tsx
  pages/learn/          # course (ikhtisar + progres), lesson (player)
  pages/classes/        # batches, students, schedule (instruktur/admin)
  pages/admin/courses/batches.tsx      # kelola batch
  components/learn/     # lesson-outline, youtube-player (IFrame API, auto-selesai)
  components/catalog/batch-picker.tsx  # pilih batch di halaman kursus & checkout
  pages/checkout/show.tsx   # ringkasan checkout (layout publik)
  pages/admin/orders/   # index, show
  components/admin/     # course-form, status/instructor cards, rich-text-editor (TipTap), curriculum/* (dnd-kit)
  components/catalog/   # course-card, course-price, course-thumbnail, pagination, public-curriculum
  components/curriculum-readonly.tsx   # kurikulum baca-saja (instruktur)
  components/seo-head.tsx   # title + meta description per halaman
  hooks/use-main-nav-items.ts   # menu sidebar sesuai role/permission
  lib/format.ts         # formatRupiah, formatDate, formatDateTime, formatDuration
  lib/roles.ts          # dashboardFor(roles), label role
tests/Feature (Admin/, Auth/, Catalog/, Payments/), tests/Unit, tests/Helpers.php (actingAsRole, sendWebhook, ...)
docs/                   # rancangan (jangan diformat ulang); docs/screenshots/ untuk PR
```

## Aturan keamanan konten (WAJIB)

- **`video_ref` tidak boleh bocor.** Lesson menyimpan hanya ID video (`video_ref`) + `video_provider`, tidak pernah URL lengkap.
  `video_ref` ada di `#[Hidden]` model `Lesson`; jangan kirim model `Lesson` mentah ke Inertia.
    - Halaman publik (`PublicCurriculum`): hanya lesson **published** di modul yang tidak terhapus; data video (`preview.embed_url`)
      **hanya** untuk lesson `is_preview = true`. Test: `tests/Feature/Catalog/CourseCurriculumTest.php`.
    - Player (`LearnController`): embed video, `summary_html`, dan `meeting_url` hanya dikirim untuk lesson yang **sudah
      terbuka** bagi siswa tersebut (`LessonPolicy::viewContent` → `ComputeLessonAccess`). Lesson terkunci hanya mengirim
      judul + tanggal buka. Test: `tests/Feature/Learning/LearnPlayerTest.php`.
    - `CurriculumLessonResource` (berisi `video_url`) hanya untuk layar staf (admin/instruktur yang ditugaskan).
- **Rich text** (`lessons.summary_html`) selalu disaring di server dengan `HtmlSanitizer` sebelum disimpan (allowlist di
  `config/purify.php` profil `rich_text`: tanpa script, event handler, style, iframe, gambar). Frontend merender HTML yang
  sudah bersih (`dangerouslySetInnerHTML` hanya untuk data ini). Toolbar editor hanya menawarkan format yang lolos allowlist.
- **Video**: semua URL video divalidasi dan di-parse lewat `VideoProvider` (rule `VideoUrl`); embed memakai `youtube-nocookie.com`.
- **Upload**: gambar sampul divalidasi (jpg/png/webp, ≤ 2 MB), disimpan di disk `public` dengan nama acak (`store()`/hashName).
- **Admin terakhir**: akun admin aktif terakhir tidak bisa dinonaktifkan, diturunkan perannya, atau menghapus akunnya sendiri
  (`User::isLastActiveAdmin()`, dipakai `UpdateUserAccount` dan `ProfileDeleteRequest`).
- **Hapus = arsip**: kursus, modul, dan lesson memakai SoftDeletes; hapus modul ikut mengarsipkan lesson-nya (`DeleteModule`).

## Role & permission

Role dan permission didefinisikan di enum `App\Enums\Role` dan `App\Enums\Permission`, lalu disinkronkan oleh
`RoleSeeder` (idempoten, aman di production). Tambah permission baru di enum, lalu jalankan ulang seeder.

| Permission           | Admin | Staf | Instruktur | Siswa | Keterangan                                        |
| -------------------- | :---: | :--: | :--------: | :---: | ------------------------------------------------- |
| `courses.manage`     |   ✓   |      |            |       | CRUD kursus, modul, lesson (Fase 2)               |
| `courses.teach`      |   ✓   |      |     ✓      |       | Kelola kursus yang ditugaskan: unlock, siswa (F4) |
| `courses.enroll`     |   ✓   |      |            |   ✓   | Beli/ambil kursus (Fase 3)                        |
| `assignments.review` |   ✓   |      |     ✓      |       | Feedback & perpanjangan tenggat, kursus miliknya  |
| `assignments.comment` |  ✓   |  ✓   |            |       | Lihat semua pengumpulan & ikut berkomentar (F5)   |
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
- Di dalam `/admin`, konten (`/admin/kursus`, `/admin/modul`, `/admin/lesson`) dikunci `permission:courses.manage` dan
  pengguna (`/admin/pengguna`) dikunci `permission:users.manage`, plus Policy per aksi. **Staf tidak boleh mengubah konten
  kursus** (403). Instruktur hanya membaca kursus yang ditugaskan (`/instruktur/kursus`, kursus lain = 404).
- Menu sidebar dibentuk dari `auth.roles`/`auth.permissions` (shared props) di `useMainNavItems` — hanya UI.

## Tugas & feedback (Fase 5)

- Tugas = gambar. Satu tugas per lesson, satu proyek akhir per kursus (`SaveAssignment`); tugas draft tidak tampil ke siswa.
- **Tenggat hanya dari `ComputeAssignmentDeadline`** (versi massal `many()`, dua query): akhir hari (`ASSIGNMENT_DUE_TIME`,
  23:59 WIB) dari acuan + `due_offset_days`. Acuan: buka lesson di batch / `ends_on` batch (proyek akhir) / `enrolled_at`
  (non-drip). Override: per siswa > per batch > default; baru harus di masa depan (`SetDeadlineOverride`).
- **Terlambat tidak pernah disimpan** — selalu `submitted_at > tenggat efektif` saat ini (`AssignmentDeadline::isLate`).
- Aturan di `SubmissionRules`: ubah pengumpulan hanya sebelum tenggat, belum `reviewed`, belum ada komentar; komentar hanya
  setelah tenggat dan untuk pengumpulan tepat waktu ("Terlambat - tidak mendapat feedback"). Komentar instruktur kursus
  pertama → `reviewed`. Staf/admin boleh berkomentar (tidak mengubah status), tidak mengubah tugas/tenggat (staf).
- **File**: validasi `AllowedImage` (finfo), normalisasi `ImageNormalizer` (wajib; format yang tidak didukung GD server
  ditolak, jangan pernah simpan file mentah), disk privat `assignments` (`serve: false`), baca hanya via
  `AttachmentController` + `AttachmentPolicy`. Jangan kirim `path`/`disk` ke props (pakai `AttachmentResource`).
  Hapus file lewat `StoreImages::delete()` (job `DeleteStoredFiles` setelah commit).
- Akses review: instruktur kursus, staf, admin (`AssignmentPolicy::canReviewCourse`), lainnya 404.
- Test gambar memakai `tests/Support/TestImages.php`; test JPEG/WebP di-skip bila GD lokal tanpa dukungan (CI punya).

## Batch, drip & progres (Fase 4)

- Kursus `drip_enabled` dijalankan per **batch** (`batches`: starts_on = Hari ke-1, ends_on, registration_closes_on,
  capacity, is_published, soft delete). Kursus drip butuh ≥ 1 batch terbit untuk diterbitkan (`PublishCourse`).
- Checkout/ambil gratis kursus drip wajib `batch_id`; `ResolveEnrollmentBatch` (lockForUpdate) menolak batch yang tidak
  terbit, selesai, pendaftaran tutup, atau penuh. Batch disimpan di `order_items.batch_id` lalu `enrollments.batch_id`.
  Pembayaran sukses **tidak pernah ditolak** karena batch penuh (dicatat di log, admin melihat "Melebihi kuota").
- `ComputeLessonAccess` adalah satu-satunya sumber kebenaran akses lesson. Prioritas: non-drip (terbuka semua) / preview >
  override per siswa > override batch > default `starts_on + drip_day_offset` pukul `LESSON_UNLOCK_TIME` (Asia/Jakarta).
  Pakai `forEnrollment()` untuk banyak lesson (satu query override). Kursus archived tetap bisa dipelajari, draft tidak.
- **Lesson yang sudah terbuka tidak pernah terkunci kembali** (`SetLessonUnlockOverride` menolak, termasuk saat reset;
  `SaveLesson` menolak memundurkan `drip_day_offset` lesson yang sudah terbuka di batch yang punya siswa).
  `lesson_unlock_overrides.scope_key` (= enrollment_id ?? 0) diisi model untuk unique NULL-safe.
- Progres: `MarkLessonCompleted`/`UnmarkLessonCompleted` → `RecalculateEnrollmentProgress` (floor, hanya lesson published).
  Progres 100% **tidak** menyelesaikan enrollment (Fase 6). Akses kedaluwarsa kursus drip dihitung dari akhir batch.
- `/kelas` hanya untuk instruktur yang ditugaskan dan admin (`CoursePolicy::manageClass`, selain itu 404); staf tidak.
  Pindah batch hanya admin (`MoveEnrollmentToBatch`, dicatat di log).

## Alur pembayaran (Fase 3)

1. Siswa (login + verified + role student) membuka `/kursus/{slug}/checkout` → POST membuat order lewat `CreateOrder`:
   hanya kursus published & berbayar, ditolak bila sudah punya akses, **harga selalu dari DB** (snapshot di
   `order_items`), order pending yang belum kedaluwarsa dipakai ulang, `order_number` `PXR-YYYYMMDD-XXXXXX`,
   `expires_at` = `ORDER_EXPIRY_HOURS` (24 jam). `StartPayment` membuat Snap token sekali dan menyimpannya di order.
2. Halaman pesanan membuka Snap.js (client key saja). Callback Snap hanya memicu cek status ke server.
3. Midtrans memanggil `POST /webhooks/midtrans` → `HandlePaymentWebhook` → `ApplyPaymentNotification`.
4. Sukses (`settlement`, atau `capture` + `fraud_status=accept`) → order `paid`, `paid_at`, enrollment lewat
   `GrantEnrollment` (`expires_at` = `access_duration_days` atau null), email `PaymentSucceeded` di-queue setelah commit.
5. `ReconcileOrder` (tombol "Cek status") mengambil Status API dan memakai pipeline yang sama.
6. Kursus gratis: `EnrollInFreeCourse` langsung membuat enrollment (`order_id` null); kursus berbayar ditolak.
7. Admin: tandai lunas manual / refund & cabut akses (`MarkOrderPaid`, `RefundOrder`) dengan catatan wajib yang dicatat
   sebagai baris `payments` gateway `manual` (pengganti activity log). Uang refund dikembalikan manual di dashboard Midtrans.

## Aturan keamanan pembayaran & webhook (WAJIB)

- **Jangan pernah mempercayai browser** (redirect Snap, query string, callback JS) sebagai bukti bayar. Status hanya dari
  notifikasi bertanda tangan atau Status API (server-to-server).
- Webhook: CSRF dikecualikan (`webhooks/*`), **signature** `SHA512(order_id + status_code + gross_amount + server_key)`
  diverifikasi dengan `hash_equals` → salah = **403**. `gross_amount` harus **persis** sama dengan `orders.total`
  (`PaymentNotification::amountMatches`) → beda = 422 + log warning, tidak ada perubahan.
- **Idempoten**: insert `webhook_events` (unik `gateway + event_id`, event_id = `transaction_id:transaction_status:status_code`)
  di dalam transaksi yang sama; duplikat dibalas 200 tanpa proses ulang. Baris order dikunci `lockForUpdate()`.
  Enrollment unik `(user_id, course_id)`; `GrantEnrollment` menangani konflik bersamaan.
- Status order hanya maju: **order `paid` tidak pernah diturunkan** (expire/deny/cancel susulan diabaikan), `refunded`
  final. Sukses yang terlambat tetap melunasi order `expired/failed` (uang sudah diterima).
- Key Midtrans hanya dari `.env` (`config/services.php`), **jangan di-log**. Log pembayaran hanya berisi nomor order/status,
  bukan payload. `payments.raw_payload`, `orders.payment_token` disembunyikan dari serialisasi; Snap token hanya dikirim
  ke pemilik order yang masih bisa dibayar.
- `FakeGateway` (kunci tanda tangan publik di kode) **ditolak di production** (`AppServiceProvider`), dan route simulasi
  tidak didaftarkan di production.
- Endpoint checkout/ambil gratis/cek status diberi rate limit; webhook `throttle:120,1`.

## Aturan kursus & kurikulum

- Status kursus: `draft → published → archived`; published boleh kembali ke draft, archived boleh ke draft/published
  (`ChangeCourseStatus`). **Publish** (`PublishCourse`) wajib: ≥ 1 lesson `published` di modul yang tidak terhapus dan
  ≥ 1 instruktur `lead`; alasan penolakan dikirim sebagai pesan validasi yang jelas. `published_at` diisi saat publish pertama.
- Kursus yang terbit harus selalu punya instruktur utama (melepas/menurunkan lead terakhir ditolak, `AssignInstructor`).
- Urutan: `modules.position` per kursus, `lessons.position` per modul (0-based). Simpan urutan hanya lewat
  `ReorderCurriculum` (satu request, satu transaksi; payload harus memuat persis semua modul & lesson kursus itu).
- Slug lesson unik per kursus (termasuk yang terhapus), dibuat sekali dan tidak berubah saat judul diganti.
- Lesson video wajib URL YouTube valid; lesson live menyimpan jadwal di `live_sessions` (beralih ke video menghapusnya).
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

php artisan migrate:fresh --seed   # reset DB + role + akun dev + katalog & kurikulum demo (local saja)
php artisan storage:link        # sekali per mesin: URL publik untuk gambar sampul (disk "public")
php artisan schedule:work       # lokal; di server: cron "* * * * * php artisan schedule:run"
php artisan orders:expire-pending     # (tiap 5 menit) order pending lewat expires_at → expired
php artisan enrollments:expire-ended  # (harian) enrollment aktif lewat expires_at → expired
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
