<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

namespace Database\Seeders;

use App\Models\User;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MerchantSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Merchant Demo',
            'email' => 'merchant@mail.com',
            'password' => Hash::make('password'),
            'role' => 'merchant',
        ]);

        Merchant::create([
            'user_id' => $user->id,
            'company_name' => 'Kopi Kenangan',
            'slug' => Str::slug('Kopi Kenangan'),
            'logo_url' => 'https://dummyimage.com/300x300',
            'qr_quota' => 10000,
            'is_verified' => true,
        ]);
    }
}

