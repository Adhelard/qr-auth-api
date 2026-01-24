<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QrBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'product_id',
        'batch_name',
        'quantity',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class);
    }
}
