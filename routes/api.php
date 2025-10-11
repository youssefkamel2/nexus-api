<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\JobController as AdminJobController;
use App\Http\Controllers\Admin\JobApplicationController as AdminJobApplicationController;
use App\Http\Controllers\Api\ServiceController as ApiServiceController;
use App\Http\Controllers\Api\ProjectController as ApiProjectController;
use App\Http\Controllers\Api\JobController as ApiJobController;
use App\Http\Controllers\Admin\AuthController as AuthController;
use App\Http\Controllers\Admin\AdminSettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\disciplineController as AdminDisciplineController;
use App\Http\Controllers\Api\disciplineController as ApiDisciplineController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Nexus Engineering API is running',
        'timestamp' => now()
    ]);
});

Route::get('/', function () {
    return response()->json(['message' => 'Welcome to Nexus Engineering API']);
});

// Admin Authentication Routes
Route::group(['prefix' => 'admin/auth'], function () {
    Route::post('/login', [AuthController::class, 'login']);
    
    // Protected auth routes
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/profile', [AuthController::class, 'profile']);

        Route::group(['prefix' => 'settings'], function () {
            Route::post('/request-update', [AdminSettingsController::class, 'requestUpdate']);
            Route::post('/confirm-update', [AdminSettingsController::class, 'confirmUpdate']);
        });

    });

});

// Admin Dashboard Routes
Route::group(['prefix' => 'admin', 'middleware' => ['auth:api']], function () {
    
    // User Management
    Route::group(['prefix' => 'users'], function () {
        Route::get('/', [AdminController::class, 'getUsers']);
        Route::get('/{encodedId}', [AdminController::class, 'getUser']);
        Route::post('/', [AdminController::class, 'createUser']);
        Route::put('/{encodedId}', [AdminController::class, 'updateUser']);
        Route::delete('/{encodedId}', [AdminController::class, 'deleteUser']);
        Route::patch('/{encodedId}/toggle-active', [AdminController::class, 'toggleActive']);
        
        // Bulk operations
        Route::post('/bulk/delete', [AdminController::class, 'bulkDelete']);
        Route::post('/bulk/update-status', [AdminController::class, 'bulkUpdateStatus']);
    });
    
    // Permission Management
    Route::group(['prefix' => 'permissions'], function () {
        Route::get('/', [AdminController::class, 'getPermissions']);
        Route::post('/assign/{encodedId}', [AdminController::class, 'assignPermissions']);
    });

    // Services Management
    Route::group(['prefix' => 'services'], function () {
        Route::get('/', [AdminServiceController::class, 'index']);
        Route::get('/{encodedId}', [AdminServiceController::class, 'show']);
        Route::get('/slug/{slug}', [AdminServiceController::class, 'getBySlug']);
        Route::post('/', [AdminServiceController::class, 'store']);
        Route::post('/{encodedId}', [AdminServiceController::class, 'update']);
        Route::delete('/{encodedId}', [AdminServiceController::class, 'destroy']);
        Route::patch('/{encodedId}/toggle-active', [AdminServiceController::class, 'toggleActive']);
        
        // Bulk operations
        Route::post('/bulk/delete', [AdminServiceController::class, 'bulkDelete']);
        Route::post('/bulk/update-status', [AdminServiceController::class, 'bulkUpdateStatus']);
    });

    // Projects Management
    Route::group(['prefix' => 'projects'], function () {
        Route::get('/', [AdminProjectController::class, 'index']);
        Route::get('/{encodedId}', [AdminProjectController::class, 'show']);
        Route::get('/slug/{slug}', [AdminProjectController::class, 'getBySlug']);
        Route::post('/', [AdminProjectController::class, 'store']);
        Route::post('/{encodedId}', [AdminProjectController::class, 'update']);
        Route::delete('/{encodedId}', [AdminProjectController::class, 'destroy']);
        Route::patch('/{encodedId}/toggle-active', [AdminProjectController::class, 'toggleActive']);
        
        // Bulk operations
        Route::post('/bulk/delete', [AdminProjectController::class, 'bulkDelete']);
        Route::post('/bulk/update-status', [AdminProjectController::class, 'bulkUpdateStatus']);
    });

    // Jobs Management
    Route::group(['prefix' => 'jobs'], function () {
        Route::get('/', [AdminJobController::class, 'index']);
        Route::get('/options', [AdminJobController::class, 'getOptions']);
        Route::get('/statistics', [AdminJobController::class, 'statistics']);
        Route::get('/{encodedId}', [AdminJobController::class, 'show']);
        Route::get('/slug/{slug}', [AdminJobController::class, 'getBySlug']);
        Route::post('/', [AdminJobController::class, 'store']);
        Route::post('/{encodedId}', [AdminJobController::class, 'update']);
        Route::delete('/{encodedId}', [AdminJobController::class, 'destroy']);
        Route::patch('/{encodedId}/toggle-active', [AdminJobController::class, 'toggleActive']);
        
        // Bulk operations
        Route::post('/bulk/delete', [AdminJobController::class, 'bulkDelete']);
        Route::post('/bulk/update-status', [AdminJobController::class, 'bulkUpdateStatus']);
    });

    // Job Applications Management
    Route::prefix('job-applications')->group(function () {
        Route::get('/', [AdminJobApplicationController::class, 'index']);
        Route::get('/status-options', [AdminJobApplicationController::class, 'getStatusOptions']);
        Route::get('/statistics', [AdminJobApplicationController::class, 'statistics']);
        Route::get('/job/{encodedId}', [AdminJobApplicationController::class, 'getByJob']);
        Route::get('/{encodedId}', [AdminJobApplicationController::class, 'show']);
        Route::patch('/{encodedId}/status', [AdminJobApplicationController::class, 'updateStatus']);
        Route::patch('/{encodedId}/notes', [AdminJobApplicationController::class, 'addNotes']);
        Route::get('/{encodedId}/download/cv', [AdminJobApplicationController::class, 'downloadDocument']);
        Route::delete('/{encodedId}', [AdminJobApplicationController::class, 'destroy']);
        
        // Bulk operations
        Route::post('/bulk/delete', [AdminJobApplicationController::class, 'bulkDelete']);
    });

    // Blog Management
    Route::prefix('blogs')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\BlogController::class, 'index']);
        Route::get('/options', [App\Http\Controllers\Admin\BlogController::class, 'getOptions']);
        Route::get('/statistics', [App\Http\Controllers\Admin\BlogController::class, 'statistics']);
        Route::post('/', [App\Http\Controllers\Admin\BlogController::class, 'store']);
        Route::get('/slug/{slug}', [App\Http\Controllers\Admin\BlogController::class, 'getBySlug']);
        Route::get('/{encodedId}', [App\Http\Controllers\Admin\BlogController::class, 'show']);
        Route::put('/{encodedId}', [App\Http\Controllers\Admin\BlogController::class, 'update']);
        Route::patch('/{encodedId}/toggle-active', [App\Http\Controllers\Admin\BlogController::class, 'toggleActive']);
        Route::post('/mark-as-hero', [App\Http\Controllers\Admin\BlogController::class, 'markAsHero']);
        Route::delete('/{encodedId}', [App\Http\Controllers\Admin\BlogController::class, 'destroy']);
        
        // Bulk operations
        Route::post('/bulk/delete', [App\Http\Controllers\Admin\BlogController::class, 'bulkDelete']);
        Route::post('/bulk/update-status', [App\Http\Controllers\Admin\BlogController::class, 'bulkUpdateStatus']);
        Route::post('/bulk-update-category', [App\Http\Controllers\Admin\BlogController::class, 'bulkUpdateCategory']);
        
        // Content image upload
        Route::post('/upload-content-image', [App\Http\Controllers\Admin\BlogController::class, 'uploadContentImage']);
    });

    Route::prefix('feedbacks')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\FeedbackController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Admin\FeedbackController::class, 'store']);
        Route::put('/{encodedId}', [App\Http\Controllers\Admin\FeedbackController::class, 'update']);
        Route::patch('/{encodedId}/toggle-active', [App\Http\Controllers\Admin\FeedbackController::class, 'toggleActive']);
        Route::delete('/{encodedId}', [App\Http\Controllers\Admin\FeedbackController::class, 'destroy']);
        
        // Bulk operations
        Route::post('/bulk/delete', [App\Http\Controllers\Admin\FeedbackController::class, 'bulkDelete']);
        Route::post('/bulk/update-status', [App\Http\Controllers\Admin\FeedbackController::class, 'bulkUpdateStatus']);
    });

    // settings
    Route::group(['prefix' => 'settings'], function () {
        Route::get('/', [App\Http\Controllers\Admin\SettingsController::class, 'index']);
        Route::put('/', [App\Http\Controllers\Admin\SettingsController::class, 'update']);
    });

    // disciplines
    Route::group(['prefix' => 'disciplines'], function () {
        Route::get('/', [AdminDisciplineController::class, 'index']);
        Route::post('/', [AdminDisciplineController::class, 'store']);
        Route::put('/{encodedId}', [AdminDisciplineController::class, 'update']);
        Route::delete('/{encodedId}', [AdminDisciplineController::class, 'destroy']);
        Route::patch('/{encodedId}/toggle-active', [AdminDisciplineController::class, 'toggleActive']);
    });


    // TEMPORARY: Cache clearing endpoint (REMOVE AFTER USE!)
    Route::post('/clear-cache', function() {
        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('permission:cache-reset');
            
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'All caches cleared successfully',
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    });
});

// Public API Routes (No Authentication Required)
Route::group(['prefix' => 'public'], function () {
    Route::group(['prefix' => 'services'], function () {
        Route::get('/', [ApiServiceController::class, 'index']);
        Route::get('/{slug}', [ApiServiceController::class, 'getBySlug']);
    });
    
    // Projects
    Route::group(['prefix' => 'projects'], function () {
        Route::get('/', [ApiProjectController::class, 'index']);
        Route::get('/{slug}', [ApiProjectController::class, 'getBySlug']);
    });
    
    // Jobs
    Route::group(['prefix' => 'jobs'], function () {
        Route::get('/', [ApiJobController::class, 'index']);
        Route::get('/locations', [ApiJobController::class, 'getLocations']);
        Route::get('/{slug}', [ApiJobController::class, 'getBySlug']);
        Route::post('/{slug}/apply', [ApiJobController::class, 'apply']);
    });

    // Blogs
    Route::group(['prefix' => 'blogs'], function () {
        Route::get('/', [App\Http\Controllers\Api\BlogController::class, 'index']);
        Route::get('/landing', [App\Http\Controllers\Api\BlogController::class, 'landing']);
        Route::get('/recent', [App\Http\Controllers\Api\BlogController::class, 'recent']);
        Route::get('/categories', [App\Http\Controllers\Api\BlogController::class, 'categories']);
        Route::get('/{slug}', [App\Http\Controllers\Api\BlogController::class, 'getBySlug']);
        Route::get('/{slug}/related', [App\Http\Controllers\Api\BlogController::class, 'related']);
    });

    // Home Page
    Route::group(['prefix' => 'home'], function () {
        Route::get('/', [App\Http\Controllers\Api\HomeController::class, 'index']);
    });

    // About Page
    Route::group(['prefix' => 'about'], function () {
        Route::get('/', [App\Http\Controllers\Api\SettingsController::class, 'index']);
    });

    // disciplines
    Route::group(['prefix' => 'disciplines'], function () {
        Route::get('/', [ApiDisciplineController::class, 'index']);
    });
});

// Legacy Sanctum route (keeping for compatibility)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
