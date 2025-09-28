<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions for Nexus Engineering admin dashboard
        $permissions = [
            // Admin Management
            'view_admins',
            'create_admins',
            'edit_admins',
            'delete_admins',
            
            // Permission Management
            'view_permissions',
            'assign_permissions',
            'revoke_permissions',
            
            // Services Management
            'view_services',
            'create_services',
            'edit_services',
            'delete_services',
            
            // Projects Management
            'view_projects',
            'create_projects',
            'edit_projects',
            'delete_projects',
            
            // Jobs Management
            'view_jobs',
            'create_jobs',
            'edit_jobs',
            'delete_jobs',
            
            // Job Applications Management
            'view_job_applications',
            'manage_job_applications',
            'delete_job_applications',

            // Blog Management
            'view_blogs',
            'create_blogs',
            'edit_blogs',
            'delete_blogs',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }

        // Create admin role (without any permissions - we'll assign permissions directly to users)
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api']);

        // Create super admin user that always has all permissions
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@nexusengineering.com',
            'password' => Hash::make('NexusAdmin2024!'),
            'profile_image' => null,
        ]);

        // Assign admin role to super admin
        $superAdmin->assignRole($adminRole);
        
        // Give super admin ALL permissions directly
        $superAdmin->syncPermissions(Permission::all());

        // Create a demo admin user for testing
        $demoAdmin = User::create([
            'name' => 'Demo Admin',
            'email' => 'demo@nexusengineering.com',
            'password' => Hash::make('DemoAdmin2024!'),
            'profile_image' => null,
        ]);

        // Assign admin role to demo user
        $demoAdmin->assignRole($adminRole);
        
        // Give demo admin limited permissions (example of selective permissions)
        $demoAdmin->syncPermissions([
            'view_admins',
            'view_services',
            'view_projects',
            'view_jobs',
            'view_job_applications',
            'view_blogs',
        ]);

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Super Admin: admin@nexusengineering.com / NexusAdmin2024!');
        $this->command->info('Demo Admin: demo@nexusengineering.com / DemoAdmin2024!');
    }
}
