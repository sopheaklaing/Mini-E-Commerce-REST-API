<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'User registered successfully',
            ])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                    'token_type',
                    'access_token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
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
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.access_token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Current user retrieved successfully',
            ]);
    }

    public function test_guest_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.access_token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User logged out successfully',
            ]);
    }

    public function test_authenticated_user_can_refresh_token(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.access_token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/refresh');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token refreshed successfully',
            ])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'token_type',
                    'access_token',
                ],
            ]);
    }
}
