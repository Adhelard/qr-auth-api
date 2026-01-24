<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

namespace Database\Seeders;

use App\Models\QrBatch;
use App\Models\Product;
use Illuminate\Database\Seeder;

class QrBatchSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::first();

        QrBatch::create([
            'merchant_id' => $product->merchant_id,
            'product_id' => $product->id,
            'batch_name' => 'Produksi Januari 2026',
            'quantity' => 10,
        ]);
    }
}

