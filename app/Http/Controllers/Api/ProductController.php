<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request)
    {
        return response()->json(
            Product::where('merchant_id', $request->user()->merchant->id)->get()
        );
    }

    // POST /api/products
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'sku'         => 'nullable|string|max:100',
            'image_url'  => 'nullable|url',
            'description'=> 'nullable|string',
        ]);

        $product = Product::create([
            ...$data,
            'merchant_id' => $request->user()->merchant->id,
            'active' => true,
        ]);

        return response()->json([
            'message' => 'Product created',
            'product' => $product
        ], 201);
    }

    // GET /api/products/{id}
    public function show(Request $request, $id)
    {
        $product = Product::where('merchant_id', $request->user()->merchant->id)
            ->findOrFail($id);

        return response()->json($product);
    }

    // PUT /api/products/{id}
    public function update(Request $request, $id)
    {
        $product = Product::where('merchant_id', $request->user()->merchant->id)
            ->findOrFail($id);

        $data = $request->validate([
            'name'        => 'string|max:255',
            'sku'         => 'nullable|string|max:100',
            'image_url'  => 'nullable|url',
            'description'=> 'nullable|string',
            'active'     => 'boolean'
        ]);

        $product->update($data);

        return response()->json([
            'message' => 'Product updated',
            'product' => $product
        ]);
    }

    // DELETE /api/products/{id}
    public function destroy(Request $request, $id)
    {
        $product = Product::where('merchant_id', $request->user()->merchant->id)
            ->findOrFail($id);

        $product->delete();

        return response()->json([
            'message' => 'Product deleted'
        ]);
    }
}
