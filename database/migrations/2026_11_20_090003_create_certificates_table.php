<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issued certificates. Recipient name, course title and batch name are
     * snapshots (profile/course edits never change an issued certificate).
     *
     * One ACTIVE certificate per enrollment: active_enrollment_id = enrollment_id
     * while valid and NULL once revoked (unique, NULL-safe), so a revoked
     * certificate stays verifiable ("dicabut") and the student can claim a new
     * one with a new number.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('active_enrollment_id')->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('certificate_number', 40)->unique();
            $table->string('verification_code', 20)->unique();
            $table->string('recipient_name', 120);
            $table->string('course_title');
            $table->string('batch_name', 100)->nullable();
            $table->dateTime('issued_at');
            // Manual issue (exception): who and why. Null for student claims.
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issue_reason', 500)->nullable();
            $table->string('pdf_path')->nullable();
            $table->dateTime('pdf_generated_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestamps();

            $table->index('enrollment_id');
            $table->index(['course_id', 'issued_at']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
