<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Midtrans Snap over Laravel's HTTP client (no SDK).
 *
 * - Snap API: POST {app}/snap/v1/transactions → token + redirect_url
 * - Status API: GET {api}/v2/{order_id}/status
 * - Notifications: signature = SHA512(order_id + status_code + gross_amount + server_key)
 */
class MidtransGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $serverKey,
        private readonly string $clientKey,
        private readonly bool $isProduction = false,
        private readonly int $timeout = 15,
        private readonly int $expiryHours = 24,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            serverKey: (string) config('services.midtrans.server_key'),
            clientKey: (string) config('services.midtrans.client_key'),
            isProduction: (bool) config('services.midtrans.is_production'),
            timeout: (int) config('services.midtrans.timeout', 15),
            expiryHours: (int) config('pixora.payments.order_expiry_hours', 24),
        );
    }

    public function key(): string
    {
        return 'midtrans';
    }

    public function createSession(Order $order): PaymentSession
    {
        $order->loadMissing(['items', 'user']);

        $minutesLeft = $order->expires_at
            ? max(1, (int) ceil(now()->diffInMinutes($order->expires_at, false)))
            : $this->expiryHours * 60;

        $body = [
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => $order->total,
            ],
            'item_details' => $order->items->map(fn (OrderItem $item) => [
                'id' => 'COURSE-'.$item->course_id,
                'price' => $item->price,
                'quantity' => 1,
                // Midtrans limits item names to 50 characters.
                'name' => Str::limit($item->title_snapshot, 47),
            ])->values()->all(),
            'customer_details' => [
                'first_name' => Str::limit($order->user->name, 50, ''),
                'email' => $order->user->email,
            ],
            'expiry' => [
                'unit' => 'minute',
                'duration' => $minutesLeft,
            ],
            'callbacks' => [
                'finish' => route('student.orders.show', $order),
            ],
        ];

        try {
            $response = $this->client($this->snapBaseUrl())->post('/snap/v1/transactions', $body);
        } catch (ConnectionException $exception) {
            throw new PaymentGatewayException('Tidak dapat terhubung ke Midtrans. Coba lagi beberapa saat.', previous: $exception);
        }

        if (! $response->successful() || ! is_string($response->json('token'))) {
            // Log status and Midtrans error messages only (no keys, no customer data).
            logger()->warning('Midtrans Snap request failed', [
                'order_number' => $order->order_number,
                'status' => $response->status(),
                'error_messages' => $response->json('error_messages'),
            ]);

            throw new PaymentGatewayException('Gagal membuat sesi pembayaran Midtrans.');
        }

        return new PaymentSession(
            token: $response->json('token'),
            redirectUrl: $response->json('redirect_url'),
        );
    }

    public function verifySignature(array $payload): bool
    {
        return MidtransPayload::verify($payload, $this->serverKey);
    }

    public function parseNotification(array $payload): PaymentNotification
    {
        return MidtransPayload::parse($payload);
    }

    public function fetchStatus(Order $order): ?PaymentNotification
    {
        try {
            $response = $this->client($this->apiBaseUrl())->get('/v2/'.rawurlencode($order->order_number).'/status');
        } catch (ConnectionException $exception) {
            throw new PaymentGatewayException('Tidak dapat terhubung ke Midtrans.', previous: $exception);
        }

        // Midtrans answers 200 with status_code "404" when no transaction exists yet.
        $statusCode = (string) $response->json('status_code', (string) $response->status());

        if ($statusCode === '404') {
            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new PaymentGatewayException('Gagal mengambil status transaksi dari Midtrans.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return MidtransPayload::parse($payload);
    }

    public function clientConfig(): array
    {
        return [
            'driver' => 'midtrans',
            'script_url' => $this->snapBaseUrl().'/snap/snap.js',
            'client_key' => $this->clientKey,
        ];
    }

    private function client(string $baseUrl): PendingRequest
    {
        if ($this->serverKey === '') {
            throw new PaymentGatewayException('MIDTRANS_SERVER_KEY belum diatur.');
        }

        return Http::baseUrl($baseUrl)
            ->withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }

    private function snapBaseUrl(): string
    {
        return $this->isProduction ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    private function apiBaseUrl(): string
    {
        return $this->isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }
}
