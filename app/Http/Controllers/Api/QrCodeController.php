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
        return response()->json(
            QrCode::whereHas('qrBatch', function ($q) use ($request) {
                $q->where('merchant_id', $request->user()->merchant->id);
            })->get()
        );
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
