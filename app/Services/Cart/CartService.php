<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

class CartService
{
    // this is for get product
    public function getCart(User $user): Cart
    {
        return Cart::firstOrCreate(
            [
                'user_id' => $user->id,
            ]
        )->load('items.product');
    }

    // this is for add to cart
    public function addItem(
        User $user,
        Product $product,
        int $quantity
    ): Cart {
        $cart = Cart::firstOrCreate([
            'user_id' => $user->id,
        ]);
        $item = $cart->items()
            ->where('product_id', $product->id)
            ->first();
        if ($item) {
            $item->increment('quantity', $quantity);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        }

        return $cart->load('items.product');
    }

    // this is for update iTems
    public function updateItem(
        User $user,
        CartItem $item,
        int $quantity
    ): Cart {
        $cart = $user->cart;

        if (! $cart || $item->cart_id != $cart->id) {
            abort(404, 'Cart item not found.');
        }

        $item->update([
            'quantity' => $quantity,
        ]);

        return $cart->load('items.product');
    }

    // for remove items
    public function removeItem(
        User $user,
        CartItem $item
    ): Cart {
        $cart = $user->cart;

        if (! $cart || $item->cart_id != $cart->id) {
            abort(404, 'Cart item not found.');
        }

        $item->delete();

        return $cart->load('items.product');
    }

    // clear cart
    public function clearCart(User $user): void
    {
        $cart = Cart::where('user_id', $user->id)->first();

        if ($cart) {
            $cart->items()->delete();
        }
    }
}
