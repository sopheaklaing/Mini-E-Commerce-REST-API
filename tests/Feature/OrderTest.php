<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_user_can_create_order_from_cart(): void
    {
        $product = Product::factory()->create([
            'price' => 100.00,
            'stock' => 10,
        ]);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/cart', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertStatus(201);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/orders', [
                'shipping_address' => 'Phnom Penh, Cambodia',
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('message', 'Order created successfully.')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.total', '200.00');

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status' => 'pending',
            'subtotal' => 200.00,
            'total' => 200.00,
            'shipping_address' => 'Phnom Penh, Cambodia',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100.00,
            'subtotal' => 200.00,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $product->id,
        ]);
    }

    public function test_user_cannot_create_order_with_empty_cart(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/orders', [
                'shipping_address' => 'Phnom Penh, Cambodia',
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Cart not found.',
            ]);
    }

    public function test_user_cannot_create_order_when_cart_is_empty(): void
    {
        Cart::create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/orders', [
                'shipping_address' => 'Phnom Penh, Cambodia',
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Cart is empty.',
            ]);
    }

    public function test_user_cannot_create_order_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create([
            'price' => 100.00,
            'stock' => 2,
        ]);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/cart', [
                'product_id' => $product->id,
                'quantity' => 5,
            ])
            ->assertStatus(201);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/orders', [
                'shipping_address' => 'Phnom Penh, Cambodia',
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => "Insufficient stock for {$product->name}.",
            ]);

        $this->assertDatabaseMissing('orders', [
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 2,
        ]);
    }

    public function test_shipping_address_is_required(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/orders');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'shipping_address',
            ]);
    }

    public function test_shipping_address_must_be_at_least_10_characters(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/orders', [
                'shipping_address' => 'Phnom',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'shipping_address',
            ]);
    }

    public function test_user_can_get_their_orders(): void
    {
        Order::factory()
            ->count(2)
            ->for($this->user)
            ->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/orders');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'order_number',
                        'status',
                        'subtotal',
                        'discount',
                        'shipping_fee',
                        'total',
                        'shipping_address',
                        'items',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_get_single_order(): void
    {
        $order = Order::factory()
            ->for($this->user)
            ->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/orders/{$order->id}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.status', $order->status);
    }

    public function test_user_cannot_access_another_users_order(): void
    {
        $anotherUser = User::factory()->create();

        $order = Order::factory()
            ->for($anotherUser)
            ->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/orders/{$order->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_cancel_pending_order(): void
    {
        $product = Product::factory()->create([
            'price' => 100.00,
            'stock' => 8,
        ]);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/cart', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertStatus(201);

        $createResponse = $this->actingAs($this->user, 'api')
            ->postJson('/api/orders', [
                'shipping_address' => 'Phnom Penh, Cambodia',
            ]);

        $createResponse->assertStatus(201);

        $orderId = $createResponse->json('data.id');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 6,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/orders/{$orderId}/cancel");

        $response
            ->assertStatus(200)
            ->assertJsonPath(
                'message',
                'Order cancelled successfully.'
            )
            ->assertJsonPath(
                'data.status',
                'cancelled'
            );

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
        ]);
    }

    public function test_user_cannot_cancel_non_pending_order(): void
    {
        $order = Order::factory()
            ->for($this->user)
            ->create([
                'status' => 'paid',
            ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/orders/{$order->id}/cancel");

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Only pending orders can be cancelled.',
            ]);
    }

    public function test_user_cannot_cancel_another_users_order(): void
    {
        $anotherUser = User::factory()->create();

        $order = Order::factory()
            ->for($anotherUser)
            ->create([
                'status' => 'pending',
            ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_order_number_is_generated_automatically(): void
    {
        $product = Product::factory()->create([
            'price' => 100.00,
            'stock' => 10,
        ]);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/cart', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertStatus(201);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/orders', [
                'shipping_address' => 'Phnom Penh, Cambodia',
            ]);

        $response->assertStatus(201);

        $orderNumber = $response->json('data.order_number');

        $this->assertNotEmpty($orderNumber);

        $this->assertMatchesRegularExpression(
            '/^ORD-\d{8}-[A-Z0-9]{6}$/',
            $orderNumber
        );
    }
}
