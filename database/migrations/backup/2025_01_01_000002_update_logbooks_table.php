<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            // Tambah kolom new_pen_category jika belum ada
            if (!Schema::hasColumn('logbooks', 'new_pen_category')) {
                $table->string('new_pen_category')->nullable()->after('new_pen_id');
            }
            // Ubah event_date dari date menjadi datetime
            $table->datetime('event_date')->change();
        });
    }

    public function down(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            $table->dropColumn('new_pen_category');
            $table->date('event_date')->change();
        });
    }
};