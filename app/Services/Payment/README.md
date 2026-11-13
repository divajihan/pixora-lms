# Services/Payment

Lapisan abstraksi payment gateway.

- `PaymentGateway` — interface (kosong di Fase 0, diisi di Fase 3).
- Implementasi pertama: `MidtransGateway` (Snap + webhook), bisa diganti Xendit tanpa mengubah Action.
- Webhook: verifikasi signature, idempoten lewat `webhook_events`, hanya percaya data dari webhook.

Lihat `docs/arsitektur-erd.md` bagian 4.A dan 7.
