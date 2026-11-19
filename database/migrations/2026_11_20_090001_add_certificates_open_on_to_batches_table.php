<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional date (00:00 app timezone) from which certificates of the batch can
     * be claimed. Null = the day after ends_on.
     */
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->date('certificates_open_on')->nullable()->after('registration_closes_on');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn('certificates_open_on');
        });
    }
};
