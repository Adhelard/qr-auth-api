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
        Schema::table('merchants', function (Blueprint $table) {
            // Hapus qr_quota lama jika mau, atau kita timpa fungsinya
            $table->dropColumn('qr_quota'); 
            
            $table->string('plan_type')->default('basic'); // basic, pro, enterprise
            $table->date('subscription_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            //
        });
    }
};
