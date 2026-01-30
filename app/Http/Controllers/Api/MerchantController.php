<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MerchantController extends Controller
{
    // GET: Ambil Data Profil
    public function show()
    {
        $user = auth()->user();
        $merchant = $user->merchant;
        $limits = $merchant->getLimits(); // Ambil limit plan

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'company_name' => $merchant->company_name,
            'slug' => $merchant->slug,
            'logo_url' => $merchant->logo_url ? asset('storage/' . $merchant->logo_url) : null,
            
            // FIX: Ganti qr_quota dengan monthly_quota dari plan
            'qr_quota' => $limits['monthly_quota'], 
            'plan_type' => $merchant->plan_type, // Tambahkan ini agar profil tau paketnya apa
            
            'is_verified' => $merchant->is_verified
        ]);
    }

    // POST/PUT: Update Profil & Logo
    public function update(Request $request)
    {
        $user = auth()->user();
        $merchant = $user->merchant;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Max 2MB
        ]);

        // 1. Update User (Nama Personal)
        $user->update(['name' => $validated['name']]);

        // 2. Update Merchant (Nama Perusahaan)
        $merchant->company_name = $validated['company_name'];
        // Opsional: Update slug jika nama perusahaan berubah (hati-hati SEO/Link lama mati)
        // $merchant->slug = Str::slug($validated['company_name']); 

        // 3. Handle Logo Upload
        if ($request->hasFile('logo')) {
            // Hapus logo lama jika ada (agar server tidak penuh)
            if ($merchant->logo_url && Storage::disk('public')->exists($merchant->logo_url)) {
                Storage::disk('public')->delete($merchant->logo_url);
            }

            // Simpan logo baru
            $path = $request->file('logo')->store('merchant-logos', 'public');
            $merchant->logo_url = $path;
        }

        $merchant->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'logo_url' => $merchant->logo_url ? asset('storage/' . $merchant->logo_url) : null,
        ]);
    }
}