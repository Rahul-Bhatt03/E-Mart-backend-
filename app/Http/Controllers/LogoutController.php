<?php

namespace App\Http\Controllers;

use App\Services\AuthServiceInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends Controller
{
   protected $authService;

    public function __construct(AuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        
        $accessTokenCookie = cookie()->forget('access_token');
        $refreshTokenCookie = cookie()->forget('refresh_token');
        
        return response()->json([
            'message' => 'Successfully logged out'
        ], Response::HTTP_OK)
        ->withCookie($accessTokenCookie)
        ->withCookie($refreshTokenCookie);
    }
}
