<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livestocks', function (Blueprint $table) {
            if (! Schema::hasColumn('livestocks', 'image_url')) {
                $table->string('image_url')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('livestocks', function (Blueprint $table) {
            if (Schema::hasColumn('livestocks', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });
    }
};
