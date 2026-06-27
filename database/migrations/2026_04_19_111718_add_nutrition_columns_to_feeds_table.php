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
        Schema::table('feeds', function (Blueprint $table) {
            // Protein Kasar (%)
            if (! Schema::hasColumn('feeds', 'pk')) {
                $table->decimal('pk', 5, 2)->nullable()->after('price_per_kg');
            }
            // Lemak Kasar (%)
            if (! Schema::hasColumn('feeds', 'lk')) {
                $table->decimal('lk', 5, 2)->nullable()->after('pk');
            }
            // Serat Kasar (%)
            if (! Schema::hasColumn('feeds', 'sk')) {
                $table->decimal('sk', 5, 2)->nullable()->after('lk');
            }
            // Abu (%)
            if (! Schema::hasColumn('feeds', 'abu')) {
                $table->decimal('abu', 5, 2)->nullable()->after('sk');
            }
            // Total Digestible Nutrients (%)
            if (! Schema::hasColumn('feeds', 'tdn')) {
                $table->decimal('tdn', 5, 2)->nullable()->after('abu');
            }
            // Neutral Detergent Fiber (%)
            if (! Schema::hasColumn('feeds', 'ndf')) {
                $table->decimal('ndf', 5, 2)->nullable()->after('tdn');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $columns = ['pk', 'lk', 'sk', 'abu', 'tdn', 'ndf'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('feeds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
