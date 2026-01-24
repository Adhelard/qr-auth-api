<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


use Illuminate\Support\Facades\DB; // <--- TAMBAHKAN INI
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Merchant;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Email atau password salah'
            ], 401);
        }

        $user = Auth::user();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ]
        ]);
    }
    public function register(Request $request)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', // butuh field password_confirmation
            'company_name' => 'required|string|max:255|unique:merchants,company_name',
        ]);

        // 2. Gunakan Transaction (User & Merchant harus sukses dua-duanya)
        $result = DB::transaction(function () use ($validated) {
            
            // A. Buat User
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'merchant', // Default role register adalah merchant
            ]);

            // B. Buat Merchant Profile
            // Slug otomatis dari nama perusahaan (contoh: "Kopi Mantap" -> "kopi-mantap")
            $slug = Str::slug($validated['company_name']); 
            
            // Cek jika slug kembar (opsional, tapi bagus untuk safety)
            $count = Merchant::where('slug', 'LIKE', "{$slug}%")->count();
            if ($count > 0) {
                $slug .= '-' . ($count + 1);
            }

            Merchant::create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'],
                'slug' => $slug,
                'qr_quota' => 100, // Bonus kuota awal (opsional)
                'is_verified' => false,
            ]);

            // C. Buat Token (Auto Login)
            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token
            ];
        });

        return response()->json([
            'message' => 'Registrasi berhasil',
            'user' => $result['user'],
            'token' => $result['token']
        ], 201);
    }
        public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

}