<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get JWT access token.
     */
    private function getToken(): string
    {
        User::factory()->create([
            'email' => 'category@test.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'category@test.com',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User logged in successfully',
            ])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'token_type',
                    'access_token',
                ],
            ]);

        return $response->json('data.access_token');
    }

    /**
     * Get authenticated request headers.
     */
    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->getToken(),
            'Accept' => 'application/json',
        ];
    }

    /**
     * Test: Get all categories.
     */
    public function test_can_get_categories(): void
    {
        Category::create([
            'name' => 'Electronics',
            'description' => 'Electronic products',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->getJson('/api/categories');

        $response
            ->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Electronics',
                'description' => 'Electronic products',
            ]);
    }

    /**
     * Test: Create category.
     */
    public function test_can_create_category(): void
    {
        $response = $this
            ->withHeaders($this->authHeaders())
            ->postJson('/api/categories', [
                'name' => 'Electronics',
                'description' => 'Electronic products',
            ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Category created successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'category' => [
                        'id',
                        'name',
                        'description',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Electronics',
            'description' => 'Electronic products',
        ]);
    }

    /**
     * Test: Category name is required.
     */
    public function test_category_name_is_required(): void
    {
        $response = $this
            ->withHeaders($this->authHeaders())
            ->postJson('/api/categories', [
                'description' => 'Electronic products',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
            ]);
    }

    /**
     * Test: Show single category.
     */
    public function test_can_get_single_category(): void
    {
        $category = Category::create([
            'name' => 'Electronics',
            'description' => 'Electronic products',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->getJson('/api/categories/'.$category->id);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Category retrieved successfully',
            ])
            ->assertJsonFragment([
                'id' => $category->id,
                'name' => 'Electronics',
                'description' => 'Electronic products',
            ]);
    }

    /**
     * Test: Update category.
     */
    public function test_can_update_category(): void
    {
        $category = Category::create([
            'name' => 'Electronics',
            'description' => 'Old description',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->putJson('/api/categories/'.$category->id, [
                'name' => 'Updated Electronics',
                'description' => 'New description',
            ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Category updated successfully',
            ])
            ->assertJsonFragment([
                'name' => 'Updated Electronics',
                'description' => 'New description',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Electronics',
            'description' => 'New description',
        ]);
    }

    /**
     * Test: Delete category.
     */
    public function test_can_delete_category(): void
    {
        $category = Category::create([
            'name' => 'Electronics',
            'description' => 'Electronic products',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->deleteJson('/api/categories/'.$category->id);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Category deleted successfully',
            ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    /**
     * Test: Guest cannot access categories.
     */
    public function test_guest_cannot_access_categories(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(401);
    }
}
