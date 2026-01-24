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
       Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_batch_id')->constrained()->cascadeOnDelete();
            $table->string('unique_code', 32)->unique();
            $table->string('serial_number');
            $table->enum('status', ['generated', 'active', 'sold'])->default('generated');
            $table->integer('scan_count')->default(0);
            $table->timestamp('first_scanned_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
