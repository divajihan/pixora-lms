<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Image assignments. lesson_id null = the course's final project.
     * At most one assignment per lesson (unique lesson_id; NULLs are allowed
     * many times) and one final project per course (final_project_key =
     * course_id when is_final_project, maintained by the model).
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions_html')->nullable();
            $table->unsignedSmallInteger('due_offset_days')->default(0);
            $table->boolean('is_final_project')->default(false);
            $table->unsignedBigInteger('final_project_key')->nullable()->unique();
            // Stored only; enforced by course completion in Phase 6.
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('max_images')->default(5);
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
