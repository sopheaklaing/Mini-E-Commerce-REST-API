<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'bill_number' => 'ORDER-'.fake()->unique()->numberBetween(1, 999999),
            'md5' => fake()->unique()->md5(),
            'amount' => 10.00,
            'currency' => 'USD',
            'method' => 'KHQR',
            'status' => 'pending',
            'qr_code' => null,
            'qr_code_url' => null,
            'expired_at' => now()->addMinutes(15),
            'paid_at' => null,
            'transaction_hash' => null,
            'from_account_id' => null,
            'to_account_id' => null,
            'external_ref' => null,
            'merchant_name' => null,
        ];
    }
}
