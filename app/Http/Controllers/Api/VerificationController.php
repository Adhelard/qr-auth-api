<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Models\ScanLog;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function verify($code, Request $request)
    {
        // 1. Cari QR Code
        $qr = QrCode::with(['qrBatch.product.merchant'])
                ->where('unique_code', $code)
                ->first();

        // 2. Jika Tidak Ditemukan (PALSU)
        if (!$qr) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Kode QR tidak dikenali sistem.',
                'data' => null
            ], 404);
        }

        // 3. Catat Log Scan (Analytics)
        // IP & User Agent otomatis dari Request
        ScanLog::create([
            'qr_code_id' => $qr->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'city' => 'Unknown', // Nanti bisa pakai GeoIP service jika mau
            'scanned_at' => now(),
        ]);

        // 4. Update Status QR Code
        $qr->increment('scan_count');
        $qr->touch('last_scanned_at');
        if (!$qr->first_scanned_at) {
            $qr->update(['first_scanned_at' => now()]);
        }

        // 5. Tentukan Status Keaslian
        // Logika: Jika discan > 10 kali, mungkin kode ini sudah di-kloning (Mencurigakan)
        $status = 'authentic';
        if ($qr->scan_count > 10) {
            $status = 'suspicious';
        }

        return response()->json([
            'status' => $status,
            'scan_count' => $qr->scan_count,
            'first_scanned_at' => $qr->first_scanned_at,
            'product' => [
                'name' => $qr->qrBatch->product->name,
                'sku' => $qr->qrBatch->product->sku,
                'image_url' => $qr->qrBatch->product->image_url,
                'description' => $qr->qrBatch->product->description,
                'merchant' => $qr->qrBatch->product->merchant->company_name,
                'batch' => $qr->qrBatch->batch_name
            ]
        ]);
    }
}