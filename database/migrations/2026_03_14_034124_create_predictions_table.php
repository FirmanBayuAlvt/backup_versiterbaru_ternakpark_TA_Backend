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
        // Tabel untuk menyimpan hasil prediksi pertumbuhan bobot ternak
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livestock_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('prediction_days');
            $table->decimal('predicted_gain', 8, 3);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->decimal('interval_lower', 8, 3)->nullable();
            $table->decimal('interval_upper', 8, 3)->nullable();
            $table->json('recommendations')->nullable();
            $table->json('input_features')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
