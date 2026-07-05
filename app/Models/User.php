<?php

namespace App\Models;

use App\Support\Menu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'email_verified_at',
        'password',
        'role',
        'branch_id',
        'access',
        'status',
        'monthly_salary',
        'last_login_at',
        'profile_photo',
        'face_image_path',
        'face_descriptors',
        'face_enrolled_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'access' => 'array',
            'monthly_salary' => 'decimal:2',
            'face_descriptors' => 'array',
            'face_enrolled_at' => 'datetime',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function attendanceEmployee()
    {
        return $this->hasOne(AttendanceEmployee::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin'], true);
    }

    public function canManageAllBranches(): bool
    {
        return $this->isAdmin();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasMenuAccess(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($key, $this->access ?? [], true);
    }

    public function accessibleMenuItems(): array
    {
        if ($this->isSuperAdmin()) {
            return Menu::items();
        }

        return array_filter(
            Menu::items(),
            fn (array $item, string $key): bool => empty($item['super_admin']) && $this->hasMenuAccess($key),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function scopeVisibleTo($query, User $viewer)
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        $query->where('role', '!=', 'super_admin');

        if ($viewer->role === 'branch_manager') {
            $query->where('branch_id', $viewer->branch_id);
        }

        return $query;
    }
}
