# Enums

PHP backed enum (`string`) untuk setiap kolom `status`/`type` di database, contoh:
`CourseStatus`, `OrderStatus`, `EnrollmentStatus`, `SubmissionStatus`.

- Kolom di database bertipe string; cast di model ke enum (`protected function casts()`).
- Nilai enum huruf kecil snake_case, sama persis dengan nilai di `docs/arsitektur-erd.md` bagian 6.
- Label untuk UI (Bahasa Indonesia) lewat method `label()` di enum.

Belum ada enum di Fase 0.
