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
        // Tabel untuk mencatat riwayat berat badan ternak
        Schema::create('weight_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livestock_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight_kg', 8, 2); // Mengubah presisi agar bisa menampung bobot dewasa hingga 200+ kg
            $table->date('record_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_records');
    }
};
