<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan kolom deleted_at pada tabel feeds untuk mendukung soft delete.
     */
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     * Menghapus kolom deleted_at dari tabel feeds.
     */
    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};