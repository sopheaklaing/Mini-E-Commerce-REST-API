<?php

namespace App\Http\Controllers\Api\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartItemRequest;
use App\Http\Requests\CartItemUpdateRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService
    ) {}

    /**
     * Display the authenticated user's cart.
     */
    public function index(): JsonResponse
    {
        $cart = $this->cartService->getCart(
            auth('api')->user()
        );

        return response()->json([
            'success' => true,
            'data' => new CartResource($cart),
        ]);
    }

    /**
     * Add a product to the cart.
     */
    public function store(CartItemRequest $request): JsonResponse
    {
        $product = Product::findOrFail(
            $request->integer('product_id')
        );

        $cart = $this->cartService->addItem(
            auth('api')->user(),
            $product,
            $request->integer('quantity')
        );

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully',
            'data' => new CartResource($cart),
        ], 201);
    }

    public function update(
        CartItemUpdateRequest $request,
        CartItem $cartItem
    ): JsonResponse {
        $cart = $this->cartService->updateItem(
            Auth::guard('api')->user(),
            $cartItem,
            $request->integer('quantity')
        );

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated successfully',
            'data' => new CartResource($cart),
        ]);
    }

    public function destroy(CartItem $cartItem): JsonResponse
    {
        $cart = $this->cartService->removeItem(
            Auth::guard('api')->user(),
            $cartItem
        );

        return response()->json([
            'success' => true,
            'message' => 'Cart item removed successfully',
            'data' => new CartResource($cart),
        ]);
    }

    public function clear(): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $this->cartService->clearCart($user);

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully',
        ]);
    }
}
