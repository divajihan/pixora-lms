<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Images of submissions and comments (morph map: submission,
     * submission_comment). Files live on a PRIVATE disk under random names and
     * are only served by AttachmentController. original_name is the client's
     * file name only (never a path).
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk', 40);
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name');
            $table->string('mime', 50);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
