<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    use ResponseTrait;
    /**
     * Create a new AdminController instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get all users with their roles and permissions
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(Request $request)
    {
        $this->authorize('view_admins');

        $users = User::with('roles', 'permissions')->where('email', '!=', 'admin@nexusengineering.com')->get()->map(function ($user) {
            return [
                'id' => $user->encoded_id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'is_active' => $user->is_active,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'created_at' => $user->created_at
            ];
        });

        return $this->success($users, 'Admins retrieved successfully');
    }

    /**
     * Get a specific user
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUser($encodedId)
    {
        $this->authorize('view_admins');

        $user = User::findByEncodedIdOrFail($encodedId);

        return $this->success([
            'admin' => [
                'id' => $user->encoded_id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'created_at' => $user->created_at
            ]
        ], 'Admin retrieved successfully');
    }

    /**
     * Create a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUser(Request $request)
    {
        $this->authorize('create_admins');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,name,guard_name,api',
            'profile_image' => 'sometimes|nullable|string'
        ]);

        // return the first error

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_image' => $request->profile_image,
        ]);

        // Assign admin role (single role approach)
        $user->assignRole('admin');

        // Assign specific permissions if provided
        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        }

        $user = $user->fresh();

        return $this->success([
            'admin' => [
                'id' => $user->encoded_id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'created_at' => $user->created_at
            ]
        ], 'Admin profile created successfully', 201);
    }

    /**
     * Update a user
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUser(Request $request, $encodedId)
    {
        $this->authorize('edit_admins');

        $user = User::findByEncodedIdOrFail($encodedId);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:8',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,name,guard_name,api',
            'profile_image' => 'sometimes|nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422);
        }

        $data = $request->only(['name', 'email', 'profile_image']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // Update permissions if provided
        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        }

        $user = $user->fresh();

        return $this->success([
            'admin' => [
                'id' => $user->encoded_id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'created_at' => $user->created_at
            ]
        ], 'Admin profile updated successfully');
    }

    /**
     * Delete a user
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser($encodedId)
    {
        $this->authorize('delete_admins');

        $user = User::findByEncodedIdOrFail($encodedId);
        
        // Prevent deleting super admin
        if ($user->email === 'admin@nexusengineering.com') {
            return $this->error('Cannot delete super admin', 403);
        }

        try {
            $user->delete();
            return $this->success(null, 'Admin deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete admin: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all permissions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPermissions()
    {
        $this->authorize('view_permissions');

        $permissions = Permission::where('guard_name', 'api')->get()->groupBy(function ($permission) {
            return explode('_', $permission->name)[0];
        });

        return $this->success([
            'permissions' => $permissions,
            'all_permissions' => Permission::where('guard_name', 'api')->get()
        ], 'Permissions retrieved successfully');
    }

    /**
     * Assign permissions to a user
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignPermissions(Request $request, $encodedId)
    {
        $this->authorize('assign_permissions');

        // make sure that user cannot update it's permissions, compare after decoding the encoded id
        $authUser = Auth::user();
        // get the actual id for the auth user
        $authUserID = $authUser->id;
        // get the actual id for the user to be updated
        $user = User::findByEncodedIdOrFail($encodedId);
        $userID = $user->id;

        if ($authUserID === $userID) {
            return $this->error('Cannot update your own permissions', 400);
        }

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name,guard_name,api'
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422);
        }

        $user = User::findByEncodedIdOrFail($encodedId);
        
        $user->syncPermissions($request->permissions);
        $user = $user->fresh();

        return $this->success([
            'admin' => [
                'id' => $user->encoded_id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'created_at' => $user->created_at
            ]
        ], 'Permissions assigned successfully');
    }

    // add toggle active status
    public function toggleActive($encodedId)
    {
        $this->authorize('edit_admins');

        try {
            $user = User::findByEncodedIdOrFail($encodedId);
            $user->update(['is_active' => !$user->is_active]);
            
            $status = $user->is_active ? 'activated' : 'deactivated';
            return $this->success([
                'admin' => [
                    'id' => $user->encoded_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile_image' => $user->profile_image,
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                    'created_at' => $user->created_at
                ]
            ], 'Admin ' . $status . ' successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to toggle admin status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk delete admins
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('delete_admins');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $deletedCount = 0;
            $errors = [];
            $currentUserId = Auth::id();

            foreach ($request->ids as $encodedId) {
                try {
                    $user = User::findByEncodedId($encodedId);
                    if ($user) {
                        // Prevent deleting yourself
                        if ($user->id === $currentUserId) {
                            $errors[] = "Cannot delete your own account";
                            continue;
                        }
                        $user->delete();
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to delete admin {$encodedId}: " . $e->getMessage();
                }
            }

            return $this->success([
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], "{$deletedCount} admin(s) deleted successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to delete admins: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk update admin status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('edit_admins');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string',
            'status' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $updatedCount = 0;
            $errors = [];
            $currentUserId = Auth::id();

            foreach ($request->ids as $encodedId) {
                try {
                    $user = User::findByEncodedId($encodedId);
                    if ($user) {
                        // Prevent deactivating yourself
                        if ($user->id === $currentUserId && !$request->status) {
                            $errors[] = "Cannot deactivate your own account";
                            continue;
                        }
                        $user->is_active = $request->status;
                        $user->save();
                        $updatedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to update admin {$encodedId}: " . $e->getMessage();
                }
            }

            $statusText = $request->status ? 'activated' : 'deactivated';
            return $this->success([
                'updated_count' => $updatedCount,
                'errors' => $errors
            ], "{$updatedCount} admin(s) {$statusText} successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to update admin status: ' . $e->getMessage(), 500);
        }
    }

}
