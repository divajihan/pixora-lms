<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feedback thread on a submission (instructor of the course, staff, admin).
     * Students read it but cannot reply. author_role is a snapshot.
     */
    public function up(): void
    {
        Schema::create('submission_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('author_role', 20);
            $table->text('body')->nullable();
            $table->dateTime('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_comments');
    }
};
