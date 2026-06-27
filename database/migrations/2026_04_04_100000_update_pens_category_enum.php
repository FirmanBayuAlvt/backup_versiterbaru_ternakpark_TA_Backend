<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mengubah kolom 'category' pada tabel 'pens' dari tipe string menjadi enum
     * dengan daftar kategori kandang yang telah ditentukan.
     */
    public function up(): void
    {
        Schema::table('pens', function (Blueprint $table) {
            $table->enum('category', [
                'Melahirkan',
                'Menyusui',
                'Kawin',
                'Karantina',
                'Persiapan Breeding',
                'Lapak',
                'Fattening',
                'Prasapih',
                'Kambing',
                'Kambing Jantan',
                'Breeding',
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Mengembalikan kolom 'category' menjadi tipe string semula.
     */
    public function down(): void
    {
        Schema::table('pens', function (Blueprint $table) {
            $table->string('category')->change();
        });
    }
};
