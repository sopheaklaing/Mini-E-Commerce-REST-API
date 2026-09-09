<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api');
    }

    public function test_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_create_product(): void
    {
        $category = Category::factory()->create();

        $data = [
            'name' => 'MacBook Air M4',
            'description' => 'Apple MacBook Air with M4 chip',
            'price' => 1199.99,
            'stock' => 15,
            'category_id' => $category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Product created successfully',
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'MacBook Air M4',
            'category_id' => $category->id,
        ]);
    }

    public function test_can_show_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson(
            "/api/products/{$product->id}"
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_update_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->putJson(
            "/api/products/{$product->id}",
            [
                'name' => 'Updated Product',
                'price' => 999.99,
                'stock' => 20,
            ]
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product updated successfully',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
        ]);
    }

    public function test_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson(
            "/api/products/{$product->id}"
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_cannot_create_product_without_required_fields(): void
    {
        $response = $this->postJson('/api/products', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'price',
                'stock',
                'category_id',
            ]);
    }

    public function test_cannot_create_product_with_invalid_price(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/products', [
            'name' => 'MacBook Air M4',
            'description' => 'Test product',
            'price' => -100,
            'stock' => 10,
            'category_id' => $category->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'price',
            ]);
    }

    public function test_cannot_create_product_with_invalid_stock(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/products', [
            'name' => 'MacBook Air M4',
            'description' => 'Test product',
            'price' => 1000,
            'stock' => -5,
            'category_id' => $category->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'stock',
            ]);
    }

    public function test_cannot_create_product_with_invalid_category(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'MacBook Air M4',
            'description' => 'Test product',
            'price' => 1000,
            'stock' => 10,
            'category_id' => 999999,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'category_id',
            ]);
    }

    public function test_cannot_update_product_with_invalid_price(): void
    {
        $product = Product::factory()->create();

        $response = $this->putJson(
            "/api/products/{$product->id}",
            [
                'price' => -100,
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'price',
            ]);
    }

    public function test_cannot_update_product_with_invalid_stock(): void
    {
        $product = Product::factory()->create();

        $response = $this->putJson(
            "/api/products/{$product->id}",
            [
                'stock' => -5,
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'stock',
            ]);
    }

    public function test_cannot_update_product_with_invalid_category(): void
    {
        $product = Product::factory()->create();

        $response = $this->putJson(
            "/api/products/{$product->id}",
            [
                'category_id' => 999999,
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'category_id',
            ]);
    }

    public function test_cannot_show_nonexistent_product(): void
    {
        $response = $this->getJson('/api/products/999999');

        $response->assertStatus(404);
    }

    public function test_cannot_update_nonexistent_product(): void
    {
        $response = $this->putJson('/api/products/999999', [
            'name' => 'Updated Product',
            'price' => 500,
            'stock' => 10,
        ]);

        $response->assertStatus(404);
    }

    public function test_cannot_delete_nonexistent_product(): void
    {
        $response = $this->deleteJson('/api/products/999999');

        $response->assertStatus(404);
    }
}
