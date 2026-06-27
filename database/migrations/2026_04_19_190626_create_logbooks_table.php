<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Membuat tabel logbooks untuk mencatat berbagai kejadian/event pada ternak
     * seperti vaksinasi, sakit, pindah kandang, melahirkan, dll.
     */
    public function up(): void
    {
        Schema::create('logbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livestock_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->date('event_date');
            $table->string('event_type'); // Contoh: Vaksin, Pindah Kandang, Sakit, Mati, Melahirkan, dll
            $table->text('description')->nullable();
            $table->string('handling')->nullable(); // Penanganan yang dilakukan
            $table->string('new_tag')->nullable(); // Tag baru jika terjadi pergantian tag
            $table->foreignId('new_pen_id')
                  ->nullable()
                  ->constrained('pens');
            $table->string('new_pen_category')->nullable(); // Kategori kandang baru jika pindah
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logbooks');
    }
};
