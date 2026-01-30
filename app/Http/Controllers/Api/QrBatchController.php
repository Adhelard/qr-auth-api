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
        $batches = QrBatch::with('product')
            ->where('merchant_id', $request->user()->merchant->id)
            ->withCount('qrCodes')
            ->latest()
            ->get()
            ->map(function ($batch) {
                // Ambil 1 QR code saja sebagai sampel metadata
                $sample = $batch->qrCodes()->select('metadata')->first();
                $batch->metadata_sample = $sample ? $sample->metadata : null;
                return $batch;
            });

        return response()->json($batches);
    }

    // POST /api/qr-batches
    // app/Http/Controllers/Api/QrBatchController.php

    public function store(Request $request)
    {
        $merchant = auth()->user()->merchant;
        $limits = $merchant->getLimits(); 
        $currentUsage = $merchant->getUsage(); 

        // 1. Validasi
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'batch_name' => 'required|string|max:255',
            'type'       => 'required|in:manual,csv',
            'quantity'   => 'required_if:type,manual|integer|min:1|max:10000',
            'csv_file'   => 'required_if:type,csv|file|mimes:csv,txt|max:2048',
            'production_date' => 'nullable|date',
        ]);

        // 2. Tentukan Jumlah & Data Source
        $csvRows = [];
        $quantity = 0;
        
        // --- PERBAIKAN: Inisialisasi $headers di sini agar selalu ada ---
        $headers = []; 
        // -------------------------------------------------------------

        if ($request->type === 'csv') {
            $file = $request->file('csv_file');
            $csvData = array_map('str_getcsv', file($file->getRealPath()));
            
            // Ambil header jika data tidak kosong
            if (!empty($csvData)) {
                $headers = array_shift($csvData);
                $csvRows = $csvData;
                $quantity = count($csvRows);
            }
        } else {
            $quantity = (int) $request->quantity;
        }

        // 3. Cek Limit
        if ($quantity > $limits['max_batch_size']) {
            return response()->json(['message' => "Maksimal {$limits['max_batch_size']} QR per batch."], 403);
        }
        if (($currentUsage + $quantity) > $limits['monthly_quota']) {
            return response()->json(['message' => "Kuota habis."], 403);
        }

        // 4. Eksekusi Transaction
        // Pastikan $headers masuk ke dalam "use (...)"
        $batch = DB::transaction(function () use ($request, $quantity, $headers, $csvRows) {
            
            $batch = QrBatch::create([
                'merchant_id' => auth()->user()->merchant->id,
                'product_id'  => $request->product_id,
                'batch_name'  => $request->batch_name,
                'quantity'    => $quantity,
            ]);

            $qrData = [];
            $now = now();
            
            for ($i = 0; $i < $quantity; $i++) {
                $serialNumber = $batch->id . '-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT);
                
                $metadata = [];

                if ($request->type === 'csv') {
                    // Cek agar tidak error jika baris CSV tidak lengkap
                    if (!empty($headers) && isset($csvRows[$i]) && count($headers) === count($csvRows[$i])) {
                        $metadata = array_combine($headers, $csvRows[$i]);
                    }
                } else {
                    if ($request->production_date) $metadata['production_date'] = $request->production_date;
                    if ($request->custom_note) $metadata['note'] = $request->custom_note;
                }

                $qrData[] = [
                    'qr_batch_id' => $batch->id,
                    'unique_code' => Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)),
                    'serial_number' => $serialNumber,
                    'metadata' => json_encode($metadata),
                    'status' => 'generated',
                    'scan_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($qrData) >= 1000) {
                    \App\Models\QrCode::insert($qrData);
                    $qrData = [];
                }
            }

            if (!empty($qrData)) {
                \App\Models\QrCode::insert($qrData);
            }

            return $batch;
        });

        return response()->json([
            'message' => 'Batch berhasil dibuat',
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
        // Set waktu unlimited agar tidak timeout saat generate banyak QR
        set_time_limit(0); 
        ini_set('memory_limit', '512M');

        try {
            // 1. Cek Extension PHP Manual
            if (!extension_loaded('gd')) {
                throw new \Exception("Extension 'GD' belum aktif di PHP. Cek php.ini Anda.");
            }
            if (!class_exists('ZipArchive')) {
                throw new \Exception("Class 'ZipArchive' tidak ditemukan. Pastikan extension 'zip' aktif di php.ini.");
            }

            // 2. Cek Permissions Folder
            $path = storage_path('app/public');
            if (!is_dir($path)) {
                mkdir($path, 0755, true); // Buat folder jika belum ada
            }
            if (!is_writable($path)) {
                throw new \Exception("Folder storage tidak bisa ditulisi (Permission Denied). Cek folder: " . $path);
            }

            // 3. Logika Utama
            $batch = \App\Models\QrBatch::with('qrCodes')->findOrFail($id);

            if ($batch->merchant_id !== auth()->user()->merchant->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $zipFileName = 'QR_Batch_' . $batch->id . '_' . time() . '.zip';
            $zipPath = $path . '/' . $zipFileName;

            $zip = new \ZipArchive;
            
            // Cek apakah ZIP berhasil dibuat/dibuka
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                throw new \Exception("Gagal membuat file ZIP di server.");
            }

            // Loop dan Generate
            foreach ($batch->qrCodes as $qr) {
                $url = "http://localhost:5173/verify/" . $qr->unique_code;
                
                // Render gambar ke string
                $image = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                            ->size(300)
                            ->margin(1)
                            ->errorCorrection('H')
                            ->generate($url);
                
                $zip->addFromString($qr->unique_code . '.png', $image);
            }

            $zip->close();

            // Pastikan file benar-benar ada sebelum didownload
            if (!file_exists($zipPath)) {
                throw new \Exception("File ZIP gagal disimpan ke disk.");
            }

            return response()->download($zipPath)->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            // Tangkap Error Apapun dan kirim ke Browser
            return response()->json([
                'error' => 'TERJADI ERROR DI SERVER',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500); 
        }
    }
}