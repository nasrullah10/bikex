<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear caches
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate tables
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('permissions')->truncate();
        DB::table('roles')->truncate();
        DB::table('admins')->truncate();
        DB::table('users')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $guard = 'api';

        // ─── PERMISSIONS ───
        $permissions = [
            'riders.view', 'riders.edit', 'riders.verify', 'riders.ban',
            'rides.view', 'rides.cancel', 'rides.dispute',
            'withdrawals.view', 'withdrawals.approve', 'withdrawals.reject',
            'subscriptions.view', 'subscriptions.manage',
            'promos.view', 'promos.manage',
            'settings.view', 'settings.edit',
            'reports.view', 'reports.export',
            'support.tickets', 'support.reply', 'support.close',
            'admins.view', 'admins.create', 'admins.edit', 'admins.delete',
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $id = (string) Str::uuid();
            $permissionIds[$perm] = $id;
            DB::table('permissions')->insert([
                'id' => $id,
                'name' => $perm,
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ─── ROLES ───
        $superAdminRoleId = (string) Str::uuid();
        $adminRoleId = (string) Str::uuid();
        $moderatorRoleId = (string) Str::uuid();
        $supportRoleId = (string) Str::uuid();

        DB::table('roles')->insert([
            ['id' => $superAdminRoleId, 'name' => 'super_admin', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $adminRoleId, 'name' => 'admin', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $moderatorRoleId, 'name' => 'moderator', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $supportRoleId, 'name' => 'support', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ─── ROLE PERMISSIONS (Direct DB Insert) ───
        $rolePermissions = [];

        // Super admin - all permissions
        foreach ($permissionIds as $permId) {
            $rolePermissions[] = ['permission_id' => $permId, 'role_id' => $superAdminRoleId];
        }

        // Admin permissions
        $adminPerms = [
            'riders.view', 'riders.edit', 'riders.verify', 'riders.ban',
            'rides.view', 'rides.cancel', 'rides.dispute',
            'withdrawals.view', 'withdrawals.approve', 'withdrawals.reject',
            'subscriptions.view', 'subscriptions.manage',
            'promos.view', 'promos.manage',
            'settings.view', 'settings.edit',
            'reports.view', 'reports.export',
            'support.tickets', 'support.reply', 'support.close',
        ];
        foreach ($adminPerms as $perm) {
            $rolePermissions[] = ['permission_id' => $permissionIds[$perm], 'role_id' => $adminRoleId];
        }

        // Moderator permissions
        $moderatorPerms = [
            'riders.view', 'riders.edit',
            'rides.view',
            'withdrawals.view',
            'subscriptions.view',
            'promos.view',
            'reports.view',
            'support.tickets', 'support.reply',
        ];
        foreach ($moderatorPerms as $perm) {
            $rolePermissions[] = ['permission_id' => $permissionIds[$perm], 'role_id' => $moderatorRoleId];
        }

        // Support permissions
        $supportPerms = [
            'riders.view',
            'rides.view',
            'subscriptions.view',
            'support.tickets', 'support.reply',
        ];
        foreach ($supportPerms as $perm) {
            $rolePermissions[] = ['permission_id' => $permissionIds[$perm], 'role_id' => $supportRoleId];
        }

        DB::table('role_has_permissions')->insert($rolePermissions);

        // ─── CREATE SUPER ADMIN USER ───
        $superAdminUserId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $superAdminUserId,
            'phone' => '+923001234567',
            'name' => 'Super Admin',
            'email' => 'superadmin@bikex.pk',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'user_type' => 'admin',
            'referral_code' => 'ADMIN001',
            'is_active' => true,
            'is_banned' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign role via direct DB insert (bypasses Spatie guard check)
        DB::table('model_has_roles')->insert([
            'role_id' => $superAdminRoleId,
            'model_type' => User::class,
            'model_id' => $superAdminUserId,
        ]);

        // Create admin record
        $adminRecordId = (string) Str::uuid();
        DB::table('admins')->insert([
            'id' => $adminRecordId,
            'user_id' => $superAdminUserId,
            'role' => 'super_admin',
            'permissions' => json_encode([]),
            'created_by' => null,
            'last_active_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ─── CREATE SAMPLE ADMIN ───
        $adminUserId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $adminUserId,
            'phone' => '+923001234568',
            'name' => 'Admin User',
            'email' => 'admin@bikex.pk',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
            'referral_code' => 'ADMIN002',
            'is_active' => true,
            'is_banned' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $adminRoleId,
            'model_type' => User::class,
            'model_id' => $adminUserId,
        ]);

        DB::table('admins')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $adminUserId,
            'role' => 'admin',
            'permissions' => json_encode(Admin::defaultPermissions('admin')),
            'created_by' => $adminRecordId,
            'last_active_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ─── CREATE SAMPLE RIDER ───
        $riderUserId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $riderUserId,
            'phone' => '+923001234569',
            'name' => 'Test Rider',
            'email' => 'rider@bikex.pk',
            'password' => Hash::make('password'),
            'user_type' => 'rider',
            'referral_code' => 'RIDER001',
            'is_active' => true,
            'is_banned' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('riders')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $riderUserId,
            'cnic_number' => '35202-1234567-1',
            'cnic_front_photo' => 'uploads/cnic/front.jpg',
            'cnic_back_photo' => 'uploads/cnic/back.jpg',
            'license_number' => 'LIC-123456',
            'license_photo' => 'uploads/license.jpg',
            'license_expiry' => now()->addYear(),
            'vehicle_type' => 'bike',
            'vehicle_company' => 'Honda',
            'vehicle_model' => 'CD 70',
            'vehicle_year' => 2022,
            'vehicle_number' => 'LHR-1234',
            'vehicle_color' => 'Black',
            'vehicle_photo' => 'uploads/vehicle.jpg',
            'verification_status' => 'approved',
            'is_online' => false,
            'is_available' => false,
            'rating' => 5.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ─── CREATE SAMPLE PASSENGER ───
        $passengerUserId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $passengerUserId,
            'phone' => '+923001234570',
            'name' => 'Test Passenger',
            'email' => 'passenger@bikex.pk',
            'password' => Hash::make('password'),
            'user_type' => 'passenger',
            'referral_code' => 'PASS001',
            'is_active' => true,
            'is_banned' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('passengers')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $passengerUserId,
            'home_address' => 'Gulberg, Lahore',
            'home_lat' => 31.5204,
            'home_lng' => 74.3587,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('✅ Seeding successful! Users, Admins, Riders, Passengers created.');
        $this->command->info('   Super Admin: superadmin@bikex.pk / password');
        $this->command->info('   Admin: admin@bikex.pk / password');
        $this->command->info('   Rider: rider@bikex.pk / password');
        $this->command->info('   Passenger: passenger@bikex.pk / password');
    }
}