<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mengubah tipe kolom breed_type dari enum (terbatas) menjadi string(100)
     * agar dapat menampung berbagai jenis domba yang lebih beragam.
     */
    public function up(): void
    {
        Schema::table('livestocks', function (Blueprint $table) {
            // Pastikan kolom breed_type ada sebelum diubah tipenya
            if (Schema::hasColumn('livestocks', 'breed_type')) {
                $table->string('breed_type', 100)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Mengembalikan tipe kolom breed_type menjadi enum dengan nilai terbatas.
     * Catatan: Proses rollback ini hanya akan berhasil jika semua nilai
     * dalam kolom breed_type sesuai dengan salah satu nilai enum yang ditentukan.
     */
    public function down(): void
    {
        Schema::table('livestocks', function (Blueprint $table) {
            if (Schema::hasColumn('livestocks', 'breed_type')) {
                $table->enum('breed_type', ['domba_lokal', 'domba_ekor_gemuk', 'domba_garut'])->change();
            }
        });
    }
};
