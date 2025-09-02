<?php

namespace App\Repositories;

interface AuthRepositoryInterface
{
    public function createUser(array $userData);
    public function findUserByEmail(string $email);
    public function findUserByUsername(string $username);
    public function revokeTokens($user);
    // the abilities will make sure to create toekn on the basis of certain permissions , like jsut to read or write or perform Crud
    public function createAccessToken($user, string $name, array $abilities = ['*']);
    public function createRefreshToken($user, string $name, array $abilities = ['*']);
}
