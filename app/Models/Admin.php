<?php

// app/Models/Admin.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Concerns\HasUuids;  

class Admin extends Model
{
    use HasFactory,HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'role',
        'permissions',
        'created_by',
        'last_active_at',
        'last_active_ip',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_active_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function createdAdmins(): HasMany
    {
        return $this->hasMany(Admin::class, 'created_by');
    }

    // ==========================================
    // ROLE CHECKS
    // ==========================================

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }

    public function isSupport(): bool
    {
        return $this->role === 'support';
    }

    // ==========================================
    // PERMISSION CHECKS
    // ==========================================

    public function hasPermission(string $permission): bool
    {
        // Super admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        return in_array($permission, $permissions);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $userPermissions = $this->permissions ?? [];

        return count(array_intersect($permissions, $userPermissions)) > 0;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $userPermissions = $this->permissions ?? [];

        return count(array_diff($permissions, $userPermissions)) === 0;
    }

    // ==========================================
    // PERMISSION CONSTANTS
    // ==========================================

    public static function allPermissions(): array
    {
        return [
            // Riders
            'riders.view',
            'riders.edit',
            'riders.verify',
            'riders.ban',

            // Rides
            'rides.view',
            'rides.cancel',
            'rides.dispute',

            // Withdrawals
            'withdrawals.view',
            'withdrawals.approve',
            'withdrawals.reject',

            // Subscriptions
            'subscriptions.view',
            'subscriptions.manage',

            // Promos
            'promos.view',
            'promos.manage',

            // Settings
            'settings.view',
            'settings.edit',

            // Reports
            'reports.view',
            'reports.export',

            // Support
            'support.tickets',
            'support.reply',
            'support.close',

            // Admins (super_admin only)
            'admins.view',
            'admins.create',
            'admins.edit',
            'admins.delete',
        ];
    }

    public static function defaultPermissions(string $role): array
    {
        return match ($role) {
            'super_admin' => self::allPermissions(),

            'admin' => [
                'riders.view', 'riders.edit', 'riders.verify', 'riders.ban',
                'rides.view', 'rides.cancel', 'rides.dispute',
                'withdrawals.view', 'withdrawals.approve', 'withdrawals.reject',
                'subscriptions.view', 'subscriptions.manage',
                'promos.view', 'promos.manage',
                'settings.view', 'settings.edit',
                'reports.view', 'reports.export',
                'support.tickets', 'support.reply', 'support.close',
            ],

            'moderator' => [
                'riders.view', 'riders.edit',
                'rides.view',
                'withdrawals.view',
                'subscriptions.view',
                'promos.view',
                'reports.view',
                'support.tickets', 'support.reply',
            ],

            'support' => [
                'riders.view',
                'rides.view',
                'subscriptions.view',
                'support.tickets', 'support.reply',
            ],

            default => [],
        };
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function updateLastActive(): void
    {
        $this->update([
            'last_active_at' => now(),
            'last_active_ip' => request()->ip(),
        ]);
    }

    public function getRoleLabel(): string
    {
        return match ($this->role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'moderator' => 'Moderator',
            'support' => 'Support Agent',
            default => 'Unknown',
        };
    }

    public function getRoleColor(): string
    {
        return match ($this->role) {
            'super_admin' => '#DC2626', // Red
            'admin' => '#F97316',       // Orange
            'moderator' => '#3B82F6',   // Blue
            'support' => '#22C55E',      // Green
            default => '#6B7280',        // Gray
        };
    }
}