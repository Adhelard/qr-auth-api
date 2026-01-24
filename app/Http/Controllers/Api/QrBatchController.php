<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrBatch;
use App\Models\Product;
use App\Services\QrGeneratorService;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class QrBatchController extends Controller
{
    // GET /api/qr-batches
    public function index(Request $request)
    {
        // Ambil batch milik merchant yang sedang login
        $batches = QrBatch::with('product')
            ->where('merchant_id', $request->user()->merchant->id)
            ->latest()
            ->get();

        return response()->json($batches);
    }

    // POST /api/qr-batches
    public function store(Request $request)
    {
        // 1. Validasi
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'batch_name' => 'required|string|max:255',
            'quantity'   => 'required|integer|min:1|max:10000', // Batasi max agar tidak timeout
        ]);

        // 2. Gunakan Transaction agar atomik (Semua tersimpan atau batal semua)
        $batch = DB::transaction(function () use ($validated) {
            
            // A. Buat Batch
            $batch = QrBatch::create([
                'merchant_id' => auth()->user()->merchant->id,
                'product_id'  => $validated['product_id'],
                'batch_name'  => $validated['batch_name'],
                'quantity'    => $validated['quantity'],
            ]);

            // B. Siapkan Data QR Codes (Bulk Insert biar cepat)
            $qrData = [];
            $now = now();
            
            for ($i = 0; $i < $validated['quantity']; $i++) {
                // Generate Serial Number: {BATCH_ID}-{URUTAN_6_DIGIT}
                // Contoh: 5-000001, 5-000002
                $serialNumber = $batch->id . '-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT);

                $qrData[] = [
                    'qr_batch_id' => $batch->id,
                    'unique_code' => Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)),
                    
                    // TAMBAHKAN BARIS INI:
                    'serial_number' => $serialNumber, 
                    
                    'status' => 'generated',
                    'scan_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Chunk Insert logic
                if (count($qrData) >= 1000) {
                    \App\Models\QrCode::insert($qrData);
                    $qrData = [];
                }
            }

            // Insert sisa data
            if (!empty($qrData)) {
                \App\Models\QrCode::insert($qrData);
            }

            return $batch;
        });

        return response()->json([
            'message' => 'Batch dan QR Codes berhasil dibuat',
            'data' => $batch
        ], 201);
    }

    // GET /api/qr-batches/{id}
    public function show(Request $request, $id)
    {
        $batch = QrBatch::with(['product', 'qrCodes'])
            ->where('merchant_id', $request->user()->merchant->id)
            ->findOrFail($id);

        return response()->json($batch);
    }

    // POST /api/qr-batches/{id}/generate
    public function generate(Request $request, $id, QrGeneratorService $service)
    {
        // Cari batch dan pastikan milik user
        $batch = QrBatch::where('merchant_id', $request->user()->merchant->id)
            ->findOrFail($id);

        // Panggil service (sesuai kode awal Anda)
        $service->generate($batch);

        return response()->json([
            'message' => 'QR berhasil digenerate',
            'batch_id' => $batch->id,
            'total' => $batch->quantity,
        ]);
    }
    public function downloadZip($id)
    {
        $batch = QrBatch::with(['qrCodes', 'product'])->findOrFail($id);

        // Cek otorisasi merchant pemilik batch
        if ($batch->merchant_id !== auth()->user()->merchant->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Siapkan nama file temporary
        $zipFileName = 'batch-' . $batch->id . '-' . time() . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName); // Simpan sementara

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            
            foreach ($batch->qrCodes as $qr) {
                // 1. Generate QR Image (Format PNG)
                // Format URL Verifikasi: https://yourdomain.com/verify/{unique_code}
                $url = config('app.frontend_url') . '/verify/' . $qr->unique_code;
                
                $image = QrCode::format('png')
                            ->size(300)
                            ->margin(1)
                            ->generate($url);
                
                // 2. Tentukan nama file dalam ZIP (misal: SKU-SerialNumber.png)
                // Pastikan unique agar tidak saling timpa
                $fileName = $batch->product->sku . '-' . ($qr->serial_number ?? $qr->unique_code) . '.png';
                
                // 3. Masukkan ke ZIP
                $zip->addFromString($fileName, $image);
            }

            $zip->close();
        }

        // Return file sebagai download dan hapus setelah dikirim
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}