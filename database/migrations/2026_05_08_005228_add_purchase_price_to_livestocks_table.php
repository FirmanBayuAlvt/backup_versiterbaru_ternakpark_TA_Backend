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
        Schema::table('livestocks', function (Blueprint $table) {
            // Menambahkan kolom purchase_price setelah kolom initial_weight
            $table->decimal('purchase_price', 15, 2)->nullable()->after('initial_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('livestocks', function (Blueprint $table) {
            // Menghapus kolom purchase_price jika rollback
            $table->dropColumn('purchase_price');
        });
    }
};