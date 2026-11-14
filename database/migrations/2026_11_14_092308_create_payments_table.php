<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // "midtrans", "fake" or "manual" (admin action).
            $table->string('gateway', 20);
            // Gateway transaction id (Midtrans transaction_id); one row per payment attempt.
            $table->string('external_id', 100)->unique();
            $table->string('payment_type', 50)->nullable();
            $table->unsignedInteger('gross_amount');
            // App\Enums\PaymentStatus
            $table->string('status', 20);
            // Latest gateway payload (or admin note for manual actions).
            $table->json('raw_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
