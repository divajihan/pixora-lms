<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Human-readable, e.g. PXR-20261114-7K3Q9M. Also the Midtrans order_id.
            $table->string('order_number', 40)->unique();
            // Integer rupiah.
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total');
            // App\Enums\OrderStatus: pending | paid | failed | expired | refunded
            $table->string('status', 20)->default('pending');
            // Gateway checkout session (Snap token/redirect URL); reused while the order is pending.
            $table->string('payment_token')->nullable();
            $table->string('payment_url', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
