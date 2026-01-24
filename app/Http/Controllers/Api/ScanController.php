<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Models\ScanLog;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function scan(Request $request, string $code)
    {
        // 1. Cari QR berdasarkan unique_code
        $qr = QrCode::with('qrBatch.product.merchant')
            ->where('unique_code', $code)
            ->first();

        // 2. Jika QR tidak ditemukan
        if (!$qr) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'QR Code tidak valid / palsu',
            ], 404);
        }

        // 3. Update scan info
        $now = now();

        if ($qr->scan_count === 0) {
            $qr->first_scanned_at = $now;
        }

        $qr->increment('scan_count');
        $qr->last_scanned_at = $now;
        $qr->save();

        // 4. Simpan log scan
        ScanLog::create([
            'qr_code_id' => $qr->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'city' => null, // nanti bisa pakai GeoIP
            'scanned_at' => $now,
        ]);

        // 5. Response sukses
        return response()->json([
            'status' => 'valid',
            'message' => 'Produk asli & terverifikasi',
            'data' => [
                'product' => [
                    'name' => $qr->qrBatch->product->name,
                    'image' => $qr->qrBatch->product->image_url,
                    'description' => $qr->qrBatch->product->description,
                ],
                'merchant' => [
                    'name' => $qr->qrBatch->product->merchant->company_name,
                    'logo' => $qr->qrBatch->product->merchant->logo_url,
                    'verified' => $qr->qrBatch->product->merchant->is_verified,
                ],
                'scan_count' => $qr->scan_count,
                'first_scanned_at' => $qr->first_scanned_at,
            ],
        ]);
    }
}
