<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Wajib import ini

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request)
    {
        // Ubah output agar image_url menjadi URL lengkap (http://...)
        $products = Product::where('merchant_id', $request->user()->merchant->id)
            ->latest()
            ->get()
            ->map(function ($product) {
                // Jika ada path gambar, tambahkan prefix storage
                if ($product->image_url && !filter_var($product->image_url, FILTER_VALIDATE_URL)) {
                    $product->image_url = asset('storage/' . $product->image_url);
                }
                return $product;
            });

        return response()->json($products);
    }

    // POST /api/products
    public function store(Request $request)
    {
        // 1. Validasi
        $request->validate([
            'name'        => 'required|string|max:255',
            'sku'         => 'required|string|max:100|unique:products,sku', // Wajib unique
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Terima File, bukan URL
            'description' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'sku', 'description']);
        
        // 2. Handle File Upload
        if ($request->hasFile('image')) {
            // Simpan ke storage/app/public/products
            $path = $request->file('image')->store('products', 'public');
            $data['image_url'] = $path; // Simpan path-nya (misal: products/abc.jpg)
        }

        // 3. Simpan ke DB
        $product = Product::create([
            ...$data,
            'merchant_id' => $request->user()->merchant->id,
            'active' => true,
        ]);

        return response()->json([
            'message' => 'Produk berhasil dibuat',
            'product' => $product
        ], 201);
    }

    // GET /api/products/{id}
    public function show(Request $request, $id)
    {
        $product = Product::where('merchant_id', $request->user()->merchant->id)
            ->findOrFail($id);
            
        if ($product->image_url && !filter_var($product->image_url, FILTER_VALIDATE_URL)) {
            $product->image_url = asset('storage/' . $product->image_url);
        }

        return response()->json($product);
    }

    // PUT /api/products/{id}
    public function update(Request $request, $id)
    {
        $product = Product::where('merchant_id', $request->user()->merchant->id)
            ->findOrFail($id);

        $request->validate([
            'name'        => 'string|max:255',
            'sku'         => 'string|max:100|unique:products,sku,'.$id, // Ignore current ID
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string',
            'active'      => 'boolean'
        ]);

        $data = $request->only(['name', 'sku', 'description', 'active']);

        // Handle Image Update
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika bukan URL eksternal
            if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
                Storage::disk('public')->delete($product->image_url);
            }

            // Upload baru
            $path = $request->file('image')->store('products', 'public');
            $data['image_url'] = $path;
        }

        $product->update($data);

        return response()->json([
            'message' => 'Produk berhasil diperbarui',
            'product' => $product
        ]);
    }

    // DELETE /api/products/{id}
    public function destroy(Request $request, $id)
    {
        $product = Product::where('merchant_id', $request->user()->merchant->id)
            ->findOrFail($id);

        // Hapus file gambar fisik agar server bersih
        if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
            Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return response()->json([
            'message' => 'Produk dihapus'
        ]);
    }
}