<?php

namespace App\Services;

use App\Models\QrBatch;
use App\Models\QrCode;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode as Qr;
use Illuminate\Support\Facades\File; // Tambahkan ini untuk cek folder

class QrGeneratorService
{
    public function generate(QrBatch $batch)
    {
        // 1. Pastikan folder penyimpanan ada. Jika tidak, buat dulu.
        $path = public_path('qrcodes');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        for ($i = 1; $i <= $batch->quantity; $i++) {

            // 2. Generate unique code
            // (Opsional: Cek collision agar benar-benar unik)
            do {
                $uniqueCode = Str::random(10);
            } while (QrCode::where('unique_code', $uniqueCode)->exists());

            // 3. Simpan ke database
            $qr = QrCode::create([
                'qr_batch_id' => $batch->id, // Pastikan nama kolom foreign key sesuai DB (batch_id atau qr_batch_id)
                'unique_code' => $uniqueCode,
                // Pastikan kolom 'serial_number' ada di migration Anda. Jika tidak, hapus baris ini.
                'serial_number' => 'SN-' . str_pad($i, 5, '0', STR_PAD_LEFT), 
                'scan_count' => 0, // Set default 0
            ]);

            // 4. Generate QR Image
            // Gunakan format SVG agar tidak butuh Imagick
            $url = config('app.url') . '/api/scan/' . $uniqueCode;

            Qr::format('svg') 
                ->size(300)
                ->errorCorrection('H') // Tingkat koreksi error (High) agar tetap terbaca meski agak rusak
                ->generate($url, public_path("qrcodes/{$qr->id}.svg")); // Simpan sebagai .svg
        }
    }
}