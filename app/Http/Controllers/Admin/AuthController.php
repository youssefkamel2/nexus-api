<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    use ResponseTrait;
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                Log::warning('Failed login attempt', ['email' => $request->email]);
                return $this->error('Authentication failed', 401);
            }
        } catch (JWTException $e) {
            Log::error('JWT token creation failed', ['error' => $e->getMessage()]);
            return $this->error('Authentication error', 500);
        }

        $user = Auth::user();

        return $this->success([
            'admin' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
            ],
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ], 'Login successful');
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        $user = Auth::user();

        return $this->success([
            'admin' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                // return the profile image url, return default image if no image
                'profile_image' => $user->profile_image ? env('APP_URL') . '/storage/' . $user->profile_image : env('APP_URL') . '/storage/profile_images/default.png',
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
            ]
        ], 'Admin profile retrieved successfully');
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return $this->success(null, 'Successfully logged out');
        } catch (JWTException $e) {
            Log::error('Logout failed', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            return $this->error('Logout error occurred', 500);
        }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            $user = Auth::user();

            return $this->success([
                'admin' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'bio' => $user->bio,
                    // return the profile image url, return default image if no image
                    'profile_image' => $user->profile_image ? env('APP_URL') . '/storage/' . $user->profile_image : env('APP_URL') . '/storage/profile_images/default.png',
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
                ],
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ], 'Token refreshed successfully');
        } catch (JWTException $e) {
            Log::error('Token refresh failed', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            return $this->error('Token refresh error', 401);
        }
    }
}
