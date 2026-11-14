<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // Day 1 of the class (drip offsets count from here), and the last day.
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('registration_closes_on')->nullable();
            // Null = unlimited.
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
