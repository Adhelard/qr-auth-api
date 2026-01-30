<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            // Kolom JSON untuk menyimpan data dinamis (warna, size, exp_date, dll)
            $table->json('metadata')->nullable()->after('serial_number');
        });
    }

    public function down()
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
