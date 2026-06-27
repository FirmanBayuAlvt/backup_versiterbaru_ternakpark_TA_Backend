<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('logbooks', function (Blueprint $table) {
            $table->dateTime('event_date')->change();
        });
    }

    public function down()
    {
        Schema::table('logbooks', function (Blueprint $table) {
            $table->date('event_date')->change();
        });
    }
};