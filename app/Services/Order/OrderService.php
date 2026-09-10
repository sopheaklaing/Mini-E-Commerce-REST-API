<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderService
{
    /**
     * Create order from user's cart.
     */
    public function createOrder(
        User $user,
        string $shippingAddress
    ): Order {
        return DB::transaction(function () use (
            $user,
            $shippingAddress
        ) {
            // Get user's cart with products
            $cart = $user->cart?->load('items.product');

            // Cart does not exist
            if (! $cart) {
                throw new RuntimeException(
                    'Cart not found.'
                );
            }

            // Cart is empty
            if ($cart->items->isEmpty()) {
                throw new RuntimeException(
                    'Cart is empty.'
                );
            }

            $subtotal = 0;

            // Validate products and stock
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;

                if (! $product) {
                    throw new RuntimeException(
                        'Product not found.'
                    );
                }

                if ($product->stock < $cartItem->quantity) {
                    throw new RuntimeException(
                        "Insufficient stock for {$product->name}."
                    );
                }

                $itemSubtotal =
                    (float) $product->price
                    * $cartItem->quantity;

                $subtotal += $itemSubtotal;
            }

            // For now
            $discount = 0;
            $shippingFee = 0;

            $total =
                $subtotal
                - $discount
                + $shippingFee;

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping_fee' => $shippingFee,
                'total' => $total,
                'shipping_address' => $shippingAddress,
            ]);

            // Create order items
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;

                $itemSubtotal =
                    (float) $product->price
                    * $cartItem->quantity;

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $cartItem->quantity,
                    'price' => $product->price,
                    'subtotal' => $itemSubtotal,
                ]);

                // Decrease product stock
                $product->decrement(
                    'stock',
                    $cartItem->quantity
                );
            }

            // Clear cart
            $cart->items()->delete();

            return $order->load('items.product');
        });
    }

    /**
     * Get all orders for the authenticated user.
     */
    public function getUserOrders(
        User $user
    ): Collection {
        return Order::with('items.product')
            ->where('user_id', $user->id)
            ->latest()
            ->get();
    }

    /**
     * Get one order belonging to the user.
     */
    public function getUserOrder(
        User $user,
        int $orderId
    ): Order {
        return Order::with('items.product')
            ->where('user_id', $user->id)
            ->findOrFail($orderId);
    }

    /**
     * Cancel pending order.
     */
    public function cancelOrder(
        User $user,
        int $orderId
    ): Order {
        return DB::transaction(function () use (
            $user,
            $orderId
        ) {
            $order = Order::with('items.product')
                ->where('user_id', $user->id)
                ->findOrFail($orderId);

            // Only pending orders can be cancelled
            if ($order->status !== 'pending') {
                throw new RuntimeException(
                    'Only pending orders can be cancelled.'
                );
            }

            // Restore stock
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment(
                        'stock',
                        $item->quantity
                    );
                }
            }

            // Update order status
            $order->update([
                'status' => 'cancelled',
            ]);

            return $order->fresh(
                'items.product'
            );
        });
    }

    /**
     * Generate unique order number.
     */
    private function generateOrderNumber(): string
    {
        do {
            $orderNumber =
                'ORD-'.
                now()->format('Ymd').
                '-'.
                strtoupper(
                    Str::random(6)
                );

        } while (
            Order::where(
                'order_number',
                $orderNumber
            )->exists()
        );

        return $orderNumber;
    }
}
