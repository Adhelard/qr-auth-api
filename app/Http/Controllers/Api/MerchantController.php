<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    // GET /api/merchant/profile
    public function show(Request $request)
    {
        $merchant = $request->user()->merchant;

        return response()->json($merchant);
    }

    // PUT /api/merchant/profile
    public function update(Request $request)
    {
        $merchant = $request->user()->merchant;

        $data = $request->validate([
            'company_name' => 'string|max:255',
            'logo_url'     => 'nullable|url',
        ]);

        $merchant->update($data);

        return response()->json([
            'message' => 'Merchant updated',
            'merchant' => $merchant
        ]);
    }
}
