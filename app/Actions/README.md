# Actions

Satu kelas = satu use case bisnis (contoh: `EnrollStudent`, `ComputeLessonAccess`, `IssueCertificate`).

- Controller tetap tipis: validasi request, panggil Action, kembalikan response.
- Kelompokkan per modul: `Enrollment/`, `Payment/`, `Learning/`, `Assignment/`, `Certificate/`.
- Gunakan satu method publik (`handle()` atau `__invoke()`); dependency lewat constructor.
- `Fortify/` berisi Action bawaan starter kit untuk registrasi dan reset password.

Lihat `docs/arsitektur-erd.md` bagian 2–4.
