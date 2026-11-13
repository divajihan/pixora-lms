# Services/Video

Lapisan abstraksi penyedia video.

- `VideoProvider` — interface (kosong di Fase 0).
- Implementasi pertama: `YouTubeProvider` (embed video unlisted); bisa diganti Bunny/Vimeo.
- `video_ref` hanya dikirim ke frontend jika lesson sudah terbuka untuk siswa tersebut.

Lihat `docs/arsitektur-erd.md` bagian 2 dan 7.
