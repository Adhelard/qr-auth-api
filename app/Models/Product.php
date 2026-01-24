<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'sku',
        'image_url',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function qrBatches()
    {
        return $this->hasMany(QrBatch::class);
    }
}


