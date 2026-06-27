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
        // Tabel utama untuk menyimpan data ternak (livestock)
        Schema::create('livestocks', function (Blueprint $table) {
            $table->id();
            $table->string('ear_tag')->unique();
            // Menggunakan string agar dapat menampung semua jenis domba (bukan enum terbatas)
            $table->string('breed_type');
            $table->enum('gender', ['male', 'female']);
            $table->date('birth_date');
            $table->decimal('initial_weight', 8, 2);
            $table->enum('health_status', ['excellent', 'good', 'fair', 'poor'])->default('good');
            $table->text('notes')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('pen_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_url')->nullable();
            $table->string('condition')->nullable();
            $table->string('reproductive_age')->nullable();
            $table->date('date_in')->nullable();
            $table->integer('day_on_farm')->default(0);
            $table->date('date_of_death_or_sold')->nullable();
            $table->string('father_ear_tag')->nullable();
            $table->string('mother_ear_tag')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livestocks');
    }
};
