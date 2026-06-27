<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            // Kolom officer_name untuk mencatat petugas IB
            if (!Schema::hasColumn('logbooks', 'officer_name')) {
                $table->string('officer_name')->nullable()->after('new_pen_category');
            }

            // Kolom pregnancy_date untuk mencatat tanggal kebuntingan
            if (!Schema::hasColumn('logbooks', 'pregnancy_date')) {
                $table->date('pregnancy_date')->nullable()->after('officer_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            if (Schema::hasColumn('logbooks', 'officer_name')) {
                $table->dropColumn('officer_name');
            }
            if (Schema::hasColumn('logbooks', 'pregnancy_date')) {
                $table->dropColumn('pregnancy_date');
            }
        });
    }
};
