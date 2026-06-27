<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migrasi untuk menambahkan kolom new_pen_category pada tabel logbooks.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            // Periksa apakah kolom new_pen_category belum ada, jika belum maka tambahkan
            if (! Schema::hasColumn('logbooks', 'new_pen_category')) {
                $table->string('new_pen_category')
                      ->nullable()
                      ->after('new_pen_id')
                      ->comment('Kategori kandang baru ketika ternak dipindahkan');
            }
        });
    }

    /**
     * Membatalkan migrasi dengan menghapus kolom new_pen_category dari tabel logbooks.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            // Periksa apakah kolom new_pen_category ada, jika ada maka hapus
            if (Schema::hasColumn('logbooks', 'new_pen_category')) {
                $table->dropColumn('new_pen_category');
            }
        });
    }
};