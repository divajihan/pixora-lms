<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drip courses run per batch: enrollments (and the order item that created
     * them) point to the batch. Non-drip courses keep batch_id null.
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('order_id')->constrained()->restrictOnDelete();
            $table->index(['batch_id', 'status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('course_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'status']);
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
