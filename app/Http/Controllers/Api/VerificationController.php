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

        // 2. Jika Tidak Ditemukan di DB (Benar-benar palsu/ngawur)
        if (!$qr) {
            return response()->json([
                'status' => 'fake', // Merah
                'message' => 'Kode QR tidak dikenali sistem.',
            ], 404);
        }

        // 3. Catat Log Scan
        ScanLog::create([
            'qr_code_id' => $qr->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'city' => 'Unknown', // Nanti bisa diisi via GeoIP Service
            'scanned_at' => now(),
        ]);

        // 4. Update Counter
        $qr->increment('scan_count');
        $qr->touch('last_scanned_at');
        if (!$qr->first_scanned_at) {
            $qr->update(['first_scanned_at' => now()]);
        }


        $maxScanLimit = 1; 

        if ($qr->scan_count > $maxScanLimit) {
            return response()->json([
                // JANGAN return 'fake', karena itu dianggap 404 (Tidak Dikenal).
                // Kita return 'suspicious' agar frontend tetap menerima data produk.
                'status' => 'suspicious', 
                'message' => 'Kode valid tetapi telah melebihi batas scan.',
                'scan_count' => $qr->scan_count,
                'product' => $this->formatProductData($qr) // Data produk tetap dikirim!
            ]);
        }

        // Jika aman (<= 5)
        return response()->json([
            'status' => 'authentic', // Hijau
            'scan_count' => $qr->scan_count,
            'first_scanned_at' => $qr->first_scanned_at,
            'product' => $this->formatProductData($qr)
        ]);
    }

    // Helper untuk merapikan data produk
    // VerificationController.php (Bagian bawah)

    private function formatProductData($qr) {
        // Cek apakah image_url adalah link luar (http) atau file lokal
        $imgUrl = $qr->qrBatch->product->image_url;
        
        if ($imgUrl && !filter_var($imgUrl, FILTER_VALIDATE_URL)) {
            // Jika file lokal, tambahkan 'storage/'
            $imgUrl = asset('storage/' . $imgUrl);
        }

        return [
            'name' => $qr->qrBatch->product->name,
            'sku' => $qr->qrBatch->product->sku,
            'image_url' => $imgUrl, // Gunakan variabel yg sudah diproses
            'description' => $qr->qrBatch->product->description,
            'merchant' => $qr->qrBatch->product->merchant->company_name,
            'merchant_logo' => $qr->qrBatch->product->merchant->logo_url ? asset('storage/'.$qr->qrBatch->product->merchant->logo_url) : null,
            'batch' => $qr->qrBatch->batch_name,
            'additional_info' => $qr->metadata
        ];
    }
}