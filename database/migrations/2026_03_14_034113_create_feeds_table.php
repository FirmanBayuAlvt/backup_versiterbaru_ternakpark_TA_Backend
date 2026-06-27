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
        // Tabel untuk menyimpan data jenis pakan
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Menggunakan string agar dapat menampung berbagai kategori pakan (Silase, Complete Feed Kediri, dll)
            $table->string('category');
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('price_per_kg', 12, 2)->nullable();
            $table->string('unit')->default('kg');
            $table->boolean('is_active')->default(true);

            // Kolom nutrisi pakan (untuk analisis formulasi pakan)
            $table->decimal('pk', 5, 2)->nullable();  // Protein Kasar (%)
            $table->decimal('lk', 5, 2)->nullable();  // Lemak Kasar (%)
            $table->decimal('sk', 5, 2)->nullable();  // Serat Kasar (%)
            $table->decimal('abu', 5, 2)->nullable(); // Abu (%)
            $table->decimal('tdn', 5, 2)->nullable(); // Total Digestible Nutrients (%)
            $table->decimal('ndf', 5, 2)->nullable(); // Neutral Detergent Fiber (%)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeds');
    }
};
