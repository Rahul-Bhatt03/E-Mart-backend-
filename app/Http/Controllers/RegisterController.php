<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AuthServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    protected $authService;

    public function __construct(AuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    public function register(Request $request)
    {
        try {
            // Validate the request data directly in the controller
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users,username',
                'email' => 'required|string|email|max:255|unique:users,email',
                'phone_number' => 'required|string|max:20|unique:users,phone_number',
                'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
                'role' => ['nullable', 'string', 'in:customer,admin'],
            ]);
            
            $user = $this->authService->register($validatedData);
            
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                ],
                'message' => 'User registered successfully'
            ], Response::HTTP_CREATED);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}