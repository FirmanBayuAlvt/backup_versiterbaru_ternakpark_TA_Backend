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
        Schema::table('pens', function (Blueprint $table) {
            // Tambah kolom abk jika belum ada
            if (!Schema::hasColumn('pens', 'abk')) {
                $table->string('abk')->nullable()->after('category');
            }
            // Tambah soft deletes jika belum ada
            if (!Schema::hasColumn('pens', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pens', function (Blueprint $table) {
            if (Schema::hasColumn('pens', 'abk')) {
                $table->dropColumn('abk');
            }
            if (Schema::hasColumn('pens', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};