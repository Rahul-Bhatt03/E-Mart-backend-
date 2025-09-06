<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller{
protected $authService;

    public function __construct(AuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }
   public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->only('email', 'password'));
            
            // Set secure HTTP-only cookies
            $accessTokenCookie = cookie(
                'access_token', 
                $result['access_token'], 
                60, // 1 hour in minutes
                null, 
                null, 
                true, // secure
                true  // httpOnly
            );
            
            $refreshTokenCookie = cookie(
                'refresh_token', 
                $result['refresh_token'], 
                60 * 24 * 15, // 15 days in minutes
                null, 
                null, 
                true, // secure
                true  // httpOnly
            );
            
            return response()->json([
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'username' => $result['user']->username,
                    'email' => $result['user']->email,
                    'phone_number' => $result['user']->phone_number,
                    'role' => $result['user']->role,
                ],
                'token'=>$result['access_token'],
                'redirect' => $result['redirect'],
                'message' => 'Login successful'
            ], Response::HTTP_OK)
            ->withCookie($accessTokenCookie)
            ->withCookie($refreshTokenCookie);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->refreshToken($request->user());
            
            $accessTokenCookie = cookie(
                'access_token', 
                $result['access_token'], 
                60,
                null, 
                null, 
                true, 
                true
            );
            
            $refreshTokenCookie = cookie(
                'refresh_token', 
                $result['refresh_token'], 
                60 * 24 * 15,
                null, 
                null, 
                true, 
                true
            );
            
            return response()->json([
                'message' => 'Token refreshed successfully'
            ], Response::HTTP_OK)
            ->withCookie($accessTokenCookie)
            ->withCookie($refreshTokenCookie);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Token refresh failed'
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}