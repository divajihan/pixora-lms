<?php

namespace Database\Seeders;

use App\Enums\CourseInstructorRole;
use App\Enums\CourseStatus;
use App\Enums\LessonStatus;
use App\Enums\LessonType;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Factories\LessonFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo catalog: 5 categories, 3 instructors, 12 courses (free/paid,
 * published/draft/archived). Idempotent. Development only (called by DevelopmentSeeder).
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            'Desain Grafis',
            'Pemrograman Web',
            'Pemasaran Digital',
            'Fotografi',
            'Bisnis & Kewirausahaan',
        ])->mapWithKeys(fn (string $name) => [
            $name => Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]),
        ]);

        $instructors = [
            'pixora' => $this->instructor('instructor@pixora.test', 'Instruktur Pixora', 'Desainer produk & pengajar UI/UX', 'Berpengalaman 8 tahun merancang produk digital untuk startup dan korporasi.'),
            'rina' => $this->instructor('rina@pixora.test', 'Rina Kartika', 'Web developer & mentor', 'Membangun aplikasi web dengan Laravel dan React sejak 2015. Senang mengajar dari nol.'),
            'budi' => $this->instructor('budi@pixora.test', 'Budi Santoso', 'Digital marketer & fotografer', 'Membantu UMKM tumbuh lewat pemasaran digital dan konten visual yang kuat.'),
        ];

        $admin = User::where('email', config('pixora.seed.admin_email'))->first();

        $courses = [
            ['Dasar Desain Grafis untuk Pemula', 'Desain Grafis', 0, CourseStatus::Published, ['pixora'], 'Pahami prinsip warna, tipografi, dan komposisi untuk membuat desain yang rapi.'],
            ['Membuat Logo yang Berkesan', 'Desain Grafis', 249_000, CourseStatus::Published, ['pixora'], 'Proses lengkap merancang logo: riset, sketsa, hingga presentasi ke klien.'],
            ['UI Design dengan Figma', 'Desain Grafis', 349_000, CourseStatus::Published, ['pixora', 'rina'], 'Rancang antarmuka aplikasi modern dan design system sederhana di Figma.'],
            ['HTML & CSS dari Nol', 'Pemrograman Web', 0, CourseStatus::Published, ['rina'], 'Bangun halaman web pertama Anda dan pahami cara kerja HTML dan CSS.'],
            ['JavaScript Modern untuk Web', 'Pemrograman Web', 299_000, CourseStatus::Published, ['rina'], 'Kuasai JavaScript ES2020+ untuk membuat halaman web yang interaktif.'],
            ['Laravel untuk Pemula', 'Pemrograman Web', 399_000, CourseStatus::Published, ['rina', 'pixora'], 'Buat aplikasi web lengkap dengan Laravel: routing, database, hingga autentikasi.'],
            ['Strategi Konten Instagram', 'Pemasaran Digital', 199_000, CourseStatus::Published, ['budi'], 'Rencanakan konten yang konsisten dan menarik untuk menumbuhkan audiens.'],
            ['Pengantar Iklan Digital', 'Pemasaran Digital', 0, CourseStatus::Published, ['budi'], 'Kenali dasar iklan berbayar dan cara mengukur hasilnya.'],
            ['Fotografi Produk dengan Ponsel', 'Fotografi', 149_000, CourseStatus::Published, ['budi'], 'Hasilkan foto produk berkualitas untuk toko online hanya dengan ponsel.'],
            ['Memulai Bisnis Online', 'Bisnis & Kewirausahaan', 179_000, CourseStatus::Draft, ['budi'], 'Langkah praktis memvalidasi ide dan meluncurkan bisnis online pertama.'],
            ['Fotografi Potret Lanjutan', 'Fotografi', 279_000, CourseStatus::Draft, ['budi'], 'Teknik pencahayaan dan pengarahan model untuk potret profesional.'],
            ['Pemasaran Email Klasik', 'Pemasaran Digital', 99_000, CourseStatus::Archived, ['budi'], 'Materi lama tentang kampanye email; digantikan kursus yang lebih baru.'],
        ];

        foreach ($courses as $index => [$title, $category, $price, $status, $teachers, $summary]) {
            $course = Course::withTrashed()->updateOrCreate(['slug' => Str::slug($title)], [
                'category_id' => $categories[$category]->id,
                'title' => $title,
                'summary' => $summary,
                'description' => $summary."\n\nKursus ini disusun bertahap dengan video singkat, rangkuman tertulis, dan tugas praktik. Cocok untuk Anda yang ingin belajar mandiri dengan jadwal fleksibel.\n\nDi akhir kursus, Anda akan memiliki hasil karya yang bisa langsung dipakai untuk portofolio.",
                'thumbnail_path' => null,
                'price' => $price,
                'is_free' => $price === 0,
                'status' => $status,
                'drip_enabled' => $index % 3 === 0,
                'access_duration_days' => $index % 4 === 0 ? 365 : null,
                'certificate_enabled' => true,
                'published_at' => $status === CourseStatus::Draft ? null : now()->subDays(60 - $index * 4),
                'created_by' => $admin?->id,
            ]);

            if ($course->trashed()) {
                $course->restore();
            }

            $course->instructors()->sync(collect($teachers)->values()->mapWithKeys(fn (string $key, int $position) => [
                $instructors[$key]->id => [
                    'role' => $position === 0 ? CourseInstructorRole::Lead->value : CourseInstructorRole::Assistant->value,
                ],
            ])->all());

            if ($status !== CourseStatus::Archived) {
                $this->curriculum($course, $index);
            }
        }
    }

    /**
     * 2–4 modules with 3–6 lessons each: mostly video, some live, the first
     * lesson is a free preview and some lessons are drafts. Deterministic per
     * course index; skipped when the course already has a curriculum.
     */
    private function curriculum(Course $course, int $seed): void
    {
        if ($course->modules()->withTrashed()->exists()) {
            return;
        }

        $moduleTitles = ['Pengenalan', 'Dasar-dasar', 'Praktik terarah', 'Proyek akhir'];
        $lessonTitles = [
            'Gambaran umum materi', 'Menyiapkan alat kerja', 'Konsep inti', 'Contoh kasus nyata',
            'Latihan bertahap', 'Kesalahan yang sering terjadi', 'Tips dari instruktur',
        ];

        $moduleCount = 2 + ($seed % 3);
        $dripDay = 0;

        for ($m = 0; $m < $moduleCount; $m++) {
            $module = $course->modules()->create([
                'title' => 'Modul '.($m + 1).': '.$moduleTitles[$m],
                'position' => $m,
            ]);

            $lessonCount = 3 + (($seed + $m) % 4);

            for ($l = 0; $l < $lessonCount; $l++) {
                $isLive = ($l + $m + $seed) % 5 === 4;
                $title = $isLive
                    ? 'Sesi live: tanya jawab '.Str::lower($moduleTitles[$m])
                    : $lessonTitles[($l + $m) % count($lessonTitles)];

                $lesson = $module->lessons()->create([
                    'course_id' => $course->id,
                    'title' => $title,
                    'slug' => Str::slug('m'.($m + 1).'-'.($l + 1).'-'.$title),
                    'type' => $isLive ? LessonType::Live : LessonType::Video,
                    'video_provider' => $isLive ? null : 'youtube',
                    'video_ref' => $isLive ? null : LessonFactory::SAMPLE_VIDEO_ID,
                    'duration_seconds' => $isLive ? 3600 : 240 + (($seed * 37 + $m * 11 + $l * 53) % 900),
                    'summary_html' => '<p>Ringkasan <strong>'.e($title).'</strong>: poin penting yang dibahas dalam lesson ini.</p><ul><li>Konsep utama</li><li>Langkah praktik</li></ul>',
                    'drip_day_offset' => $course->drip_enabled ? $dripDay : 0,
                    'position' => $l,
                    // Last lesson of every other module is still a draft.
                    'status' => ($l === $lessonCount - 1 && $m % 2 === 1) ? LessonStatus::Draft : LessonStatus::Published,
                    'is_preview' => $m === 0 && $l === 0,
                ]);

                if ($isLive) {
                    $startsAt = now()->startOfDay()->addDays(7 + $m * 7 + $l)->setTime(19, 0);
                    $lesson->liveSession()->create([
                        'starts_at' => $startsAt,
                        'ends_at' => $startsAt->addHour(),
                        'platform' => 'Zoom',
                        'meeting_url' => 'https://meet.example.test/pixora-'.$course->id.'-'.$lesson->id,
                        'notes' => 'Tautan aktif 10 menit sebelum sesi dimulai.',
                    ]);
                }

                $dripDay++;
            }
        }
    }

    private function instructor(string $email, string $name, string $headline, string $bio): User
    {
        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
        ]);

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now(), 'is_active' => true])->save();
        $user->assignRole(Role::Instructor->value);
        $user->instructorProfile()->updateOrCreate([], ['headline' => $headline, 'bio' => $bio]);

        return $user;
    }
}
