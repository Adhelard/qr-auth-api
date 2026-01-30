<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_name', 'slug', 'logo_url', 'is_verified', 
        'plan_type', 'subscription_expires_at'
    ];

    // DEFINISI PAKET LANGGANAN (LIMITS)
    public const PLANS = [
        'basic' => [
            'name' => 'Basic Plan',
            'max_batch_size' => 100,      // Sekali generate max 100 QR
            'monthly_quota' => 1000,      // Total jatah QR per bulan
            'price' => 150000
        ],
        'pro' => [
            'name' => 'Pro Plan',
            'max_batch_size' => 1000,     // Sekali generate max 1.000 QR
            'monthly_quota' => 10000,     // Total jatah QR per bulan
            'price' => 450000
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'max_batch_size' => 10000,    // Sekali generate max 10.000 QR
            'monthly_quota' => 100000,    // Total jatah QR per bulan
            'price' => 1200000
        ]
    ];

    // Helper untuk ambil limit paket merchant saat ini
    public function getLimits()
    {
        return self::PLANS[$this->plan_type] ?? self::PLANS['basic'];
    }

    // Helper untuk cek sisa kuota (Logic sederhana: hitung total QR yg dibuat)
    public function getUsage()
    {
        // Hitung total QR yang dimiliki merchant ini dari semua batch
        return \App\Models\QrCode::whereHas('qrBatch.product.merchant', function($q) {
            $q->where('id', $this->id);
        })->count();
    }

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function qrBatches()
    {
        return $this->hasMany(QrBatch::class);
    }
}


