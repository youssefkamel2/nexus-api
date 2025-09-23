<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::user();

        // Super admin always has access
        if ($user->email === 'admin@nexusengineering.com') {
            return $next($request);
        }

        // Check if user has the required permission
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource',
                'required_permission' => $permission
            ], 403);
        }

        return $next($request);
    }
}
