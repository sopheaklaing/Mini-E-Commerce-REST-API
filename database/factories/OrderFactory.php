<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(fake()->bothify('??????')),
            'status' => 'pending',
            'subtotal' => 100.00,
            'discount' => 0.00,
            'shipping_fee' => 0.00,
            'total' => 100.00,
            'shipping_address' => 'Phnom Penh, Cambodia',
        ];
    }
}
