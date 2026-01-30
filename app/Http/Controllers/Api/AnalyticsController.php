<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    // GET /api/analytics/summary
    public function summary(Request $request)
    {
        $merchant = auth()->user()->merchant;
        
        // AMBIL LIMIT BERDASARKAN PAKET (Fix Disini)
        $limits = $merchant->getLimits(); // Mengambil array dari Model Merchant

        $productIds = $merchant->products()->pluck('id');
        $batchIds = $merchant->qrBatches()->pluck('id');

        // 1. Hitung Total Scans
        $totalScans = QrCode::whereIn('qr_batch_id', $batchIds)->sum('scan_count');

        // 2. Hitung Suspicious
        $suspiciousScans = QrCode::whereIn('qr_batch_id', $batchIds)
                            ->where('scan_count', '>', 5)
                            ->sum('scan_count');

        // 3. Hitung Kuota Terpakai
        $quotaUsed = QrCode::whereIn('qr_batch_id', $batchIds)->count();
        
        // 4. Recent Scans logic (tetap sama)
        $recentScans = ScanLog::whereHas('qrCode', function ($q) use ($batchIds) {
                            $q->whereIn('qr_batch_id', $batchIds);
                        })
                        ->with(['qrCode.qrBatch.product'])
                        ->latest('scanned_at')
                        ->take(5)
                        ->get()
                        ->map(function ($log) {
                            return [
                                'product' => $log->qrCode->qrBatch->product->name,
                                'location' => $log->city == 'Unknown' ? $log->ip_address : $log->city,
                                'time' => $log->scanned_at->diffForHumans(),
                                'status' => $log->qrCode->scan_count > 5 ? 'suspicious' : 'valid'
                            ];
                        });

        // 5. DATA CHART (Agar grafik tidak random terus di frontend)
        // Kita buat data dummy statistik 7 hari terakhir (atau query real jika mau)
        $chartData = [0, 0, 0, 0, 0, 0, 0]; // Nanti bisa diganti query group by date

        return response()->json([
            'total_products' => $merchant->products()->count(),
            'total_batches' => $merchant->qrBatches()->count(),
            'total_scans' => (int) $totalScans,
            'suspicious_scans' => (int) $suspiciousScans,
            'quota_used' => $quotaUsed,
            
            // FIX: Ambil limit dari Plan, bukan kolom qr_quota
            'quota_limit' => $limits['monthly_quota'], 
            
            // FIX: Kirim plan_type agar label di dashboard benar
            'plan_type' => $merchant->plan_type, 

            'recent_scans' => $recentScans,
            'chart_data' => $chartData
        ]);
    }

    // GET /api/analytics/product/{id}
    public function product(Request $request, $id)
    {
        $merchantId = $request->user()->merchant->id;

        // Pastikan produk milik merchant
        $product = Product::where('merchant_id', $merchantId)->findOrFail($id);

        // Hitung scan spesifik untuk produk ini
        $productScans = QrCode::whereHas('qrBatch', function ($q) use ($product) {
            $q->where('product_id', $product->id);
        })->sum('scan_count');

        // Opsional: Grafik scan per hari (7 hari terakhir)
        // Query ini mungkin perlu disesuaikan dengan database driver (MySQL/PostgreSQL)
        $chartData = ScanLog::whereHas('qrCode.qrBatch', function ($q) use ($product) {
            $q->where('product_id', $product->id);
        })
        ->where('scanned_at', '>=', now()->subDays(7))
        ->select(DB::raw('DATE(scanned_at) as date'), DB::raw('count(*) as count'))
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        return response()->json([
            'product' => $product->name,
            'total_scans' => $productScans,
            'chart_data' => $chartData
        ]);
    }
}