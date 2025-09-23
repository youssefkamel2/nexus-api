# Nexus Engineering - Development Structure Guide

## Overview
This guide outlines the standardized structure and patterns used in the Nexus Engineering project for consistent development across all modules.

## Project Architecture

### Directory Structure
```
nexus/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/           # Admin dashboard controllers
│   │   │   └── Api/             # Public API controllers
│   │   ├── Resources/           # API response resources
│   │   └── Middleware/          # Custom middleware
│   ├── Models/                  # Eloquent models
│   └── Traits/                  # Reusable traits
├── database/
│   ├── migrations/              # Database migrations
│   └── seeders/                 # Database seeders
├── routes/
│   └── api.php                  # API routes
└── storage/
    └── app/public/              # File uploads
```

## Standard Implementation Pattern

### 1. Database Layer

#### Migration Template
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
};
```

#### Model Template
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelName extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }
}
```

### 2. API Resource Layer

#### Resource Template
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModelNameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'email' => $this->author->email,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### 3. Controller Layer

#### Admin Controller Template
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModelNameResource;
use App\Models\ModelName;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ModelNameController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index()
    {
        $this->authorize('view_model_names');
        
        $items = ModelName::with('author')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'is_active' => $item->is_active,
                'author' => [
                    'id' => $item->author->id,
                    'name' => $item->author->name,
                    'email' => $item->author->email,
                ],
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return $this->success($items, 'Items retrieved successfully');
    }

    public function show(ModelName $modelName)
    {
        $this->authorize('view_model_names');
        return $this->success(new ModelNameResource($modelName->load('author')), 'Item retrieved successfully');
    }

    public function store(Request $request)
    {
        $this->authorize('create_model_names');

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:table_name,slug',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();
            $data['created_by'] = Auth::id();

            $item = ModelName::create($data);
            return $this->success(new ModelNameResource($item->load('author')), 'Item created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create item: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, ModelName $modelName)
    {
        $this->authorize('edit_model_names');

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:table_name,slug,' . $modelName->id,
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $modelName->update($validator->validated());
            return $this->success(new ModelNameResource($modelName->fresh()->load('author')), 'Item updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update item: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(ModelName $modelName)
    {
        $this->authorize('delete_model_names');

        try {
            $modelName->delete();
            return $this->success(null, 'Item deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete item: ' . $e->getMessage(), 500);
        }
    }

    public function toggleActive(ModelName $modelName)
    {
        $this->authorize('edit_model_names');

        try {
            $modelName->update(['is_active' => !$modelName->is_active]);
            $status = $modelName->is_active ? 'activated' : 'deactivated';
            
            return $this->success([
                'item' => [
                    'id' => $modelName->id,
                    'title' => $modelName->title,
                    'is_active' => $modelName->is_active,
                ]
            ], "Item {$status} successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to toggle item status: ' . $e->getMessage(), 500);
        }
    }
}
```

#### Public API Controller Template
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModelNameResource;
use App\Models\ModelName;
use App\Traits\ResponseTrait;

class ModelNameController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        $items = ModelName::with('author')->active()->get();
        return $this->success(ModelNameResource::collection($items), 'Items retrieved successfully');
    }

    public function getBySlug($slug)
    {
        $item = ModelName::with('author')->active()->bySlug($slug)->first();

        if (!$item) {
            return $this->error('Item not found', 404);
        }

        return $this->success(new ModelNameResource($item), 'Item retrieved successfully');
    }
}
```

### 4. Routes Structure

#### API Routes Template
```php
// Admin Routes (Authentication Required)
Route::group(['prefix' => 'admin', 'middleware' => ['auth:api']], function () {
    
    // Module Management
    Route::group(['prefix' => 'module-name'], function () {
        Route::get('/', [AdminModuleController::class, 'index']);
        Route::get('/{model}', [AdminModuleController::class, 'show']);
        Route::get('/slug/{slug}', [AdminModuleController::class, 'getBySlug']);
        Route::post('/', [AdminModuleController::class, 'store']);
        Route::put('/{model}', [AdminModuleController::class, 'update']);
        Route::delete('/{model}', [AdminModuleController::class, 'destroy']);
        Route::patch('/{model}/toggle-active', [AdminModuleController::class, 'toggleActive']);
    });
});

// Public Routes (No Authentication)
Route::group(['prefix' => 'public'], function () {
    Route::group(['prefix' => 'module-name'], function () {
        Route::get('/', [ApiModuleController::class, 'index']);
        Route::get('/{slug}', [ApiModuleController::class, 'getBySlug']);
    });
});
```

### 5. Permissions Structure

#### Permission Naming Convention
```php
// Standard permissions for each module
'view_{module_name}',      // View list and details
'create_{module_name}',    // Create new items
'edit_{module_name}',      // Edit existing items
'delete_{module_name}',    // Delete items
```

#### Seeder Addition Template
```php
// Add to RolesAndPermissionsSeeder.php
// Module Name Management
'view_{module_name}',
'create_{module_name}',
'edit_{module_name}',
'delete_{module_name}'
```

### 6. File Upload Structure

#### Storage Locations
```
storage/app/public/
├── {module_name}/
│   ├── covers/          # Main images
│   ├── sections/        # Section images
│   └── documents/       # Documents/files
```

#### Upload Handling Template
```php
// In controller store/update methods
if ($request->hasFile('image_field')) {
    $data['image_field'] = $request->file('image_field')->store('{module_name}/covers', 'public');
}
```

### 7. Response Structure

#### Success Response
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": {
        // Response data here
    }
}
```

#### Error Response
```json
{
    "success": false,
    "message": "Error description"
}
```

### 8. Validation Rules

#### Common Validation Patterns
```php
// Text fields
'title' => 'required|string|max:255',
'slug' => 'required|string|max:255|unique:table_name,slug',
'content' => 'required|string',

// Files
'image' => 'required|image|max:4096',
'document' => 'sometimes|file|mimes:pdf,doc,docx|max:10240',

// Boolean
'is_active' => 'sometimes|boolean',

// Arrays
'tags' => 'sometimes|array',
'tags.*' => 'string|max:50',
```

## Postman Collection Structure

### Collection Organization
```
Collection Name
├── Admin {Module} Management
│   ├── Get All Items
│   ├── Get Item by ID
│   ├── Get Item by Slug
│   ├── Create New Item
│   ├── Update Item
│   ├── Toggle Active Status
│   └── Delete Item
├── Public {Module} API
│   ├── Get All Active Items
│   └── Get Item by Slug (Public)
└── Authentication (For Testing)
    └── Login
```

### Collection Variables
```json
{
    "base_url": "http://localhost/nexus/public/api",
    "jwt_token": ""
}
```

## Development Checklist

### For Each New Module:
- [ ] Create migration with standard fields
- [ ] Create model with relationships and scopes
- [ ] Create API resource for response formatting
- [ ] Create admin controller with full CRUD
- [ ] Create public API controller
- [ ] Add permissions to seeder
- [ ] Add routes (admin + public)
- [ ] Create Postman collection
- [ ] Test all endpoints
- [ ] Update documentation

### Standard Fields for All Modules:
- [ ] `id` (primary key)
- [ ] `title` (string)
- [ ] `slug` (unique string)
- [ ] `is_active` (boolean, default true)
- [ ] `created_by` (foreign key to users)
- [ ] `created_at` / `updated_at` (timestamps)

### Standard Methods for All Controllers:
- [ ] `index()` - List all items
- [ ] `show()` - Get single item
- [ ] `getBySlug()` - Get by slug
- [ ] `store()` - Create new item
- [ ] `update()` - Update existing item
- [ ] `destroy()` - Delete item
- [ ] `toggleActive()` - Toggle active status

This structure ensures consistency, maintainability, and scalability across all modules in the Nexus Engineering project.
