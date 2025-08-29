<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{

  public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('min') && is_numeric($request->min)) {
            $query->where('price', '>=', $request->min);
        }

        if ($request->has('max') && is_numeric($request->max)) {
            $query->where('price', '<=', $request->max);
        }

        $perPage = $request->get('per_page', 6);

        $products = $query->paginate($perPage);

        return response()->json($products);
    }
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name'  => 'string|max:255',
            'price' => 'numeric|min:0',
            'stock' => 'integer|min:0',
        ]);

        $product->update($data);

        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
