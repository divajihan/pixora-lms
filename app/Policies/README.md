# Policies

Semua otorisasi lewat Laravel Policy (contoh: `CoursePolicy`, `LessonPolicy`, `SubmissionPolicy`, `OrderPolicy`).

- Pengecekan akses selalu di server; menyembunyikan tombol di UI bukan otorisasi.
- Role/permission memakai `spatie/laravel-permission` (Admin, Staf, Instruktur, Siswa) — dibuat di Fase 1.
- Aturan "instruktur kursus ini" memakai tabel `course_instructors`.

Belum ada policy di Fase 0.
