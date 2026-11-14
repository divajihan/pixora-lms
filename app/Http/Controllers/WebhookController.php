<?php

namespace App\Http\Controllers;

use App\Actions\Payments\HandlePaymentWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /webhooks/midtrans — public, CSRF-exempt, signature-verified.
 * Answers fast; heavy work (emails) is queued.
 */
class WebhookController extends Controller
{
    public function midtrans(Request $request, HandlePaymentWebhook $handle): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all() ?: $request->all();

        $result = $handle->handle($payload);

        return response()->json(['message' => $result['message']], $result['status']);
    }
}
