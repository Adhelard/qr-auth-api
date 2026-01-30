<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use Illuminate\Http\Request;

class QrCodeController extends Controller
{
    // GET /api/qr-codes
   public function index(Request $request)
    {
        // 1. Mulai Query
        $query = QrCode::query();

        // 2. Filter Berdasarkan Batch ID (Ini yang kurang sebelumnya)
        if ($request->has('batch_id')) {
            $query->where('qr_batch_id', $request->batch_id);
        }

        // 3. Keamanan: Pastikan hanya mengambil QR milik Merchant yang sedang login
        // Agar merchant A tidak bisa melihat QR merchant B
        $query->whereHas('qrBatch', function($q) {
            $q->where('merchant_id', auth()->user()->merchant->id);
        });

        // 4. Ambil Data (Gunakan pagination agar ringan jika datanya ribuan)
        // Frontend svelte Anda sudah siap menerima format pagination (res.data)
        $qrCodes = $query->paginate(100); 

        return response()->json($qrCodes);
    }

    // GET /api/qr-codes/{id}
    public function show(Request $request, $id)
    {
        $qrCode = QrCode::whereHas('qrBatch', function ($q) use ($request) {
            $q->where('merchant_id', $request->user()->merchant->id);
        })->findOrFail($id);

        return response()->json($qrCode);
    }
}
