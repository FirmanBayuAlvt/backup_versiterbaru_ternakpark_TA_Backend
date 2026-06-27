<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('livestocks', function (Blueprint $table) {
            // Kolom: kondisi ternak (Menyusui, Bakalan, Cacat, dll)
            if (! Schema::hasColumn('livestocks', 'condition')) {
                $table->string('condition')->nullable()->after('health_status');
            }

            // Kolom: tanggal masuk ternak ke kandang
            if (! Schema::hasColumn('livestocks', 'date_in')) {
                $table->date('date_in')->nullable()->after('birth_date');
            }

            // Kolom: lama hari ternak berada di farm
            if (! Schema::hasColumn('livestocks', 'day_on_farm')) {
                $table->integer('day_on_farm')->default(0)->after('date_in');
            }

            // Kolom: usia reproduksi ternak (jika ada)
            if (! Schema::hasColumn('livestocks', 'reproductive_age')) {
                $table->string('reproductive_age')->nullable()->after('condition');
            }

            // Kolom: tanggal ternak keluar (mati atau terjual)
            if (! Schema::hasColumn('livestocks', 'date_of_death_or_sold')) {
                $table->date('date_of_death_or_sold')->nullable()->after('status');
            }

            // Kolom: ear tag induk jantan
            if (! Schema::hasColumn('livestocks', 'father_ear_tag')) {
                $table->string('father_ear_tag')->nullable()->after('notes');
            }

            // Kolom: ear tag induk betina
            if (! Schema::hasColumn('livestocks', 'mother_ear_tag')) {
                $table->string('mother_ear_tag')->nullable()->after('father_ear_tag');
            }

            // Kolom: URL foto ternak
            if (! Schema::hasColumn('livestocks', 'image_url')) {
                $table->string('image_url')->nullable()->after('mother_ear_tag');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('livestocks', function (Blueprint $table) {
            $columns = [
                'condition',
                'date_in',
                'day_on_farm',
                'reproductive_age',
                'date_of_death_or_sold',
                'father_ear_tag',
                'mother_ear_tag',
                'image_url',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('livestocks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
