<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// TEMPORARY CACHE CLEAR ROUTE - REMOVE AFTER USE!
// Access via: yourdomain.com/clear-cache-production-2024
Route::get('/clear-cache-production-2024', function() {
    try {
        // Clear all caches
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('permission:cache-reset');
        
        $results = [
            'config:clear' => Artisan::output(),
            'cache:clear' => 'Application cache cleared',
            'route:clear' => 'Route cache cleared',
            'view:clear' => 'View cache cleared',
            'permission:cache-reset' => 'Permission cache reset',
            'timestamp' => now()->toDateTimeString(),
            'status' => 'SUCCESS'
        ];
        
        return response()->json($results, 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'ERROR',
            'message' => $e->getMessage(),
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
});
