<?php

namespace App\Enums;

/**
 * Normalised payment attempt status (payments.status), independent of the
 * gateway's own vocabulary (Midtrans transaction_status / fraud_status).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Success => 'Berhasil',
            self::Failed => 'Gagal/ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
            self::Refunded => 'Dikembalikan',
        };
    }
}
