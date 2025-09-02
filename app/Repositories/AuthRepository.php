<?php

namespace App\Repositories;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;


class AuthRepository implements AuthRepositoryInterface
{
    public function  createUser(array $userData)
    {
        return User::create($userData);
    }
    public function findUserByEmail(string $email)
    {
        return User::where('email', $email)->first();
    }
    public function findUserByUsername(string $username)
    {
        return User::where('username', $username)->first();
    }
    public function revokeTokens($user)
    {
        $user->tokens()->delete();
    }
    public function createAccessToken($user, string $name, array $abilities = ['*'])
    {
        return $user->createToken($name, $abilities, now()->addMinutes(60))->plainTextToken;
    }

    public function createRefreshToken($user, string $name, array $abilities = ['*'])
    {
        return $user->createToken($name, $abilities, now()->addDays(15))->plainTextToken;
    }
}
