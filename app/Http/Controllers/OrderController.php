<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller

{public function index(Request $request)
{
    $user = Auth::user();
    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $query = Order::with('product')
        ->where('user_id', $user->id);

    if ($request->has('from')) {
        $query->whereDate('created_at', '>=', $request->from);
    }
    if ($request->has('to')) {
        $query->whereDate('created_at', '<=', $request->to);
    }

    $perPage = $request->get('per_page', 10);
    $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

    return response()->json($orders);
}

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            $items = $request->items;

            $orders = [];

            DB::transaction(function () use ($items, $user, &$orders) {
                foreach ($items as $item) {
                    $product = Product::lockForUpdate()->find($item['product_id']);

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Not enough quantity ");
                    }

                    $product->stock -= $item['quantity'];
                    $product->save();

                    $order = Order::create([
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'total' => $product->price * $item['quantity'],
                        'status' => 'pending',
                    ]);

                    $orders[] = $order;
                }
            });

            return response()->json([
                'message' => 'success ',
                'orders' => $orders
            ]);
        } catch (\Exception $error) {
            Log::error('Order failed', [
                'message' => $error->getMessage(),
                'line' => $error->getLine(),
                'file' => $error->getFile(),
            ]);
            return response()->json(['error' => $error->getMessage()], 400);
        }
    }
}
