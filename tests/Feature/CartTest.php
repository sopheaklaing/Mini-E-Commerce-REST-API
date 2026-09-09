<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->product = Product::factory()->create([
            'stock' => 20,
        ]);
    }

    public function test_authenticated_user_can_get_cart(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/cart');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('carts', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_authenticated_user_can_add_product_to_cart(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Product added to cart successfully',
            ]);

        $cart = Cart::where('user_id', $this->user->id)->first();

        $this->assertNotNull($cart);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
    }

    public function test_authenticated_user_can_update_cart_item(): void
    {
        $cart = Cart::create([
            'user_id' => $this->user->id,
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/cart/items/{$item->id}", [
                'quantity' => 5,
            ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cart item updated successfully',
            ]);

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 5,
        ]);
    }

    public function test_authenticated_user_can_remove_cart_item(): void
    {
        $cart = Cart::create([
            'user_id' => $this->user->id,
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/cart/items/{$item->id}");

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cart item removed successfully',
            ]);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $item->id,
        ]);
    }

    public function test_authenticated_user_can_clear_cart(): void
    {
        $cart = Cart::create([
            'user_id' => $this->user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $secondProduct = Product::factory()->create([
            'stock' => 20,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $secondProduct->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson('/api/cart');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cart cleared successfully',
            ]);

        $this->assertDatabaseCount('cart_items', 0);

        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_guest_cannot_access_cart(): void
    {
        $response = $this->getJson('/api/cart');

        $response->assertStatus(401);
    }
}
