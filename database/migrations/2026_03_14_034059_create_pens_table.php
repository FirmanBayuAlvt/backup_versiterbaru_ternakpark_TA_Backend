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
        // Tabel kandang (pen) untuk menampung data lokasi ternak
        Schema::create('pens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            // Menggunakan tipe string agar dapat menyimpan berbagai kategori kandang
            // (Fattening, Kawin, Melahirkan, Karantina, dll)
            $table->string('category');
            $table->integer('capacity');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pens');
    }
};
