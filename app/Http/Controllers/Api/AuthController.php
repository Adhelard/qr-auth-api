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
            // FIX: Gunakan load('merchant') agar data plan_type terbawa ke frontend
            'user' => $user->load('merchant'), 
        ]);
    }
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8',
            'company_name' => 'required|string|unique:merchants',
            'plan' => 'required|in:basic,pro,enterprise', 
        ]);

        // Hasil return dari dalam transaction akan masuk ke variabel $result
        $result = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'merchant',
            ]);

            $slug = Str::slug($validated['company_name']);
            
            Merchant::create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'],
                'slug' => $slug,
                'plan_type' => $validated['plan'],
                'is_verified' => false,
                'subscription_expires_at' => now()->addMonth(), 
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Kembalikan array agar bisa ditangkap oleh $result di luar
            return ['user' => $user, 'token' => $token];
        });

        // FIX: Ambil data dari $result, bukan variabel $token/$user langsung
        return response()->json([
            'token' => $result['token'],
            'user' => $result['user']->load('merchant'), 
        ]);
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