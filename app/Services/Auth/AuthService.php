<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'password' => Hash::make((string) $data['password']),
        ]);

        $token = Auth::guard('api')->login($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @param  array<string, string>  $credentials
     */
    public function login(array $credentials): ?string
    {
        $token = Auth::guard('api')->attempt($credentials);

        return is_string($token) ? $token : null;
    }

    public function logout(): void
    {
        Auth::guard('api')->logout();
    }

    public function refresh(): string
    {
        $token = JWTAuth::getToken();

        if ($token === null) {
            throw new \RuntimeException('Token not provided.');
        }

        return (string) JWTAuth::refresh($token);
    }

    public function me(): ?User
    {
        return Auth::guard('api')->user();
    }
}
