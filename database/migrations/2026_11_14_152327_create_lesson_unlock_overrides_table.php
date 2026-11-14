<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom unlock dates. enrollment_id null = the whole batch; otherwise one
     * student. Priority: per-enrollment > per-batch > default schedule.
     * No "is_locked": an opened lesson is never locked again.
     */
    public function up(): void
    {
        Schema::create('lesson_unlock_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('unlock_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // NULL-safe uniqueness: unique indexes allow many NULLs, so the batch-wide
            // row (enrollment_id NULL) is keyed as 0. Maintained by the model
            // (LessonUnlockOverride::saving) — a generated column is not allowed here
            // because MySQL forbids ON DELETE CASCADE on base columns of stored
            // generated columns.
            $table->unsignedBigInteger('scope_key')->default(0);
            $table->timestamps();

            $table->unique(['lesson_id', 'batch_id', 'scope_key'], 'lesson_unlock_overrides_scope_unique');
            $table->index(['batch_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_unlock_overrides');
    }
};
