<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            // Integer rupiah, no decimals. 0 for free courses.
            $table->unsignedInteger('price')->default(0);
            $table->boolean('is_free')->default(false);
            // App\Enums\CourseStatus: draft | published | archived
            $table->string('status', 20)->default('draft');
            $table->boolean('drip_enabled')->default(false);
            // Null = lifetime access.
            $table->unsignedInteger('access_duration_days')->nullable();
            $table->boolean('certificate_enabled')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
