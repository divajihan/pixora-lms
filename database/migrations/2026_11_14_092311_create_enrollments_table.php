<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            // Null for free courses.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\EnrollmentStatus: active | completed | expired | revoked
            $table->string('status', 20)->default('active');
            $table->timestamp('enrolled_at');
            // Null = lifetime access.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamps();

            // No duplicate enrollments, even under concurrent webhooks.
            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
