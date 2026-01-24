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
        $merchantId = $request->user()->merchant->id;

        // 1. Total Produk
        $totalProducts = Product::where('merchant_id', $merchantId)->count();

        // 2. Total Scan (Sum scan_count dari semua QR milik merchant)
        $totalScans = QrCode::whereHas('qrBatch', function ($q) use ($merchantId) {
            $q->where('merchant_id', $merchantId);
        })->sum('scan_count');

        // 3. Log Scan Terakhir (5 Data)
        // Kita butuh join karena ScanLog biasanya tidak langsung punya merchant_id
        $recentScans = ScanLog::whereHas('qrCode.qrBatch', function ($q) use ($merchantId) {
            $q->where('merchant_id', $merchantId);
        })
        ->with(['qrCode.qrBatch.product']) // Load relasi untuk nama produk
        ->latest('scanned_at')
        ->take(5)
        ->get()
        ->map(function ($log) {
            return [
                'product_name' => $log->qrCode->qrBatch->product->name ?? 'Unknown',
                'unique_code' => $log->qrCode->unique_code,
                'location' => $log->city ?? 'Unknown',
                'scanned_at' => $log->scanned_at,
            ];
        });

        return response()->json([
            'total_products' => $totalProducts,
            'total_scans' => $totalScans,
            'recent_scans' => $recentScans
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