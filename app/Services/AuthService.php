<?php

namespace App\Services;

use App\Repositories\AuthRepository;
use App\Repositories\AuthRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    protected $authRepository;
    public function __construct(AuthRepositoryInterface $authRepository)
    {
        $this->authRepository = $authRepository;
    }
    public function register(array $userData)
    {
        $userData['password'] = Hash::make($userData['password']);
        // Set default role if not provided
        $userData['role'] = $userData['role'] ?? 'customer';
        return $this->authRepository->createUser($userData);
    }
    public function login(array $credentials)
    {
        $user = $this->authRepository->findUserByEmail($credentials['email']) ?? $this->authRepository->findUserByUsername($credentials['email']);
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }
       $accessToken = $this->authRepository->createAccessToken($user, 'access-token', ['access-api']);
        $refreshToken = $this->authRepository->createRefreshToken($user, 'refresh-token', ['refresh-api']);
        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'redirect' => $user->role === 'admin' ? '/admin' : '/'
        ];
    }
    public function logout($user)
    {
        $this->authRepository->revokeTokens($user);
    }
     public function refreshToken($user)
    {
        $this->authRepository->revokeTokens($user);
        
        $accessToken = $this->authRepository->createAccessToken($user, 'access-token', ['access-api']);
        $refreshToken = $this->authRepository->createRefreshToken($user, 'refresh-token', ['refresh-api']);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken
        ];
    }
}
