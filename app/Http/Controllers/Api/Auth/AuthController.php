<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => new UserResource($result['user']),
                'access_token' => $result['token'],
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->authService->login(
            $request->validated()
        );
        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully',
        ]);
    }

    public function refresh(): JsonResponse
    {
        $token = $this->authService->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        $user = $this->authService->me();

        return response()->json([
            'success' => true,
            'message' => 'Current user retrieved successfully',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }
}
