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
            // Menambahkan kolom abk (nama penanggung jawab kandang)
            if (! Schema::hasColumn('pens', 'abk')) {
                $table->string('abk')->nullable()->after('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pens', function (Blueprint $table) {
            // Menghapus kolom abk jika rollback
            $table->dropColumn('abk');
        });
    }
};
