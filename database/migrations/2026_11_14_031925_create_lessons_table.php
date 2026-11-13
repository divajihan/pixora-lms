<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            // App\Enums\LessonType: video | live
            $table->string('type', 20)->default('video');
            // VideoProvider key (e.g. "youtube") and the provider's video id only — never a full URL.
            $table->string('video_provider', 20)->nullable();
            $table->string('video_ref', 100)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            // Sanitised server-side (config/purify.php "rich_text") before saving.
            $table->longText('summary_html')->nullable();
            // Day N after enrollment (0 = open immediately). Stored only; unlock logic is Phase 4.
            $table->unsignedInteger('drip_day_offset')->default(0);
            // Order within the module (0-based).
            $table->unsignedInteger('position')->default(0);
            // App\Enums\LessonStatus: draft | published
            $table->string('status', 20)->default('draft');
            $table->boolean('is_preview')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['course_id', 'slug']);
            $table->index(['course_id', 'position']);
            $table->index(['module_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
