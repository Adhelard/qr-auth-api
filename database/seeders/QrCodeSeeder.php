<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

namespace Database\Seeders;

use App\Models\QrBatch;
use App\Models\QrCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QrCodeSeeder extends Seeder
{
    public function run(): void
    {
        $batch = QrBatch::first();

        for ($i = 1; $i <= $batch->quantity; $i++) {
            QrCode::create([
                'qr_batch_id' => $batch->id,
                'unique_code' => Str::random(10),
                'serial_number' => 'SN-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'status' => 'active',
            ]);
        }
    }
}

