<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Merchant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::first();

        Product::create([
            'merchant_id' => $merchant->id,
            'name' => 'Kopi Susu Gula Aren',
            'sku' => 'KOPI-001',
            'image_url' => 'https://dummyimage.com/400x400',
            'description' => 'Kopi susu khas gula aren',
            'active' => true,
        ]);
    }
}

