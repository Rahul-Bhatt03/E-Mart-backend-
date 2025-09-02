<?php
// app/Services/AuthServiceInterface.php

namespace App\Services;

interface AuthServiceInterface
{
    public function register(array $userData);
    public function login(array $credentials);
    public function logout($user);
    public function refreshToken($user);
}