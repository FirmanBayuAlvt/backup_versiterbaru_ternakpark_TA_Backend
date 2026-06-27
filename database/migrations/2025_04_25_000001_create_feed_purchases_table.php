<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membuat tabel untuk mencatat pembelian/pengadaan pakan dari supplier.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('feed_purchases', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('supplier')->nullable();
            $table->string('feed_name');
            $table->decimal('price_per_unit', 12, 2)->nullable();
            $table->decimal('quantity', 10, 2);
            $table->string('unit')->default('kg');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indeks untuk mempercepat query filtering berdasarkan tanggal dan nama pakan
            $table->index('date');
            $table->index('feed_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_purchases');
    }
};
