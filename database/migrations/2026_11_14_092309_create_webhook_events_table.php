<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            // Midtrans: transaction_id:transaction_status:status_code.
            $table->string('event_id', 191);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Idempotency: each gateway event is processed once.
            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
