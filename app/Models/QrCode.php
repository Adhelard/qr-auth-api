<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'qr_batch_id',
        'unique_code',
        'serial_number',
        'status',
        'scan_count',
        'first_scanned_at',
        'last_scanned_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'first_scanned_at' => 'datetime',
        'last_scanned_at' => 'datetime',
    ];

    public function qrBatch()
    {
        return $this->belongsTo(QrBatch::class);
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class);
    }
}

