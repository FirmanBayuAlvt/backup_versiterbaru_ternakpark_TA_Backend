<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Membuat tabel hpp_details untuk menyimpan rincian Harga Pokok Produksi per ternak.
     */
    public function up(): void
    {
        Schema::create('hpp_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livestock_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->decimal('feed_cost', 15, 2)->default(0);
            $table->decimal('operational_cost', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hpp_details');
    }
};
