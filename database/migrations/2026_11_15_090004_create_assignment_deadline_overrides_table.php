<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deadline extensions. Exactly one scope: a batch (batch_id) or one student
     * (enrollment_id). Priority: enrollment > batch > default. scope_key
     * ("batch:ID" / "enrollment:ID", set by the model) keeps one row per scope.
     */
    public function up(): void
    {
        Schema::create('assignment_deadline_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope_key', 40);
            $table->dateTime('due_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assignment_id', 'scope_key']);
            $table->index('batch_id');
            $table->index('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_deadline_overrides');
    }
};
