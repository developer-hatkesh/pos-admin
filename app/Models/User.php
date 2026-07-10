<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Status;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'company_id',
        'avatar_path',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => Status::class,
        ];
    }

    public function setRoleAttribute(UserRole|string|null $role): void
    {
        $this->attributes['role'] = $role instanceof UserRole ? $role->value : $role;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin';
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class)->withTimestamps();
    }

    public function createdJournalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'created_by');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin()
            || $this->legacyRoleValue() === UserRole::Admin->value;
    }

    public function isSuperAdmin(): bool
    {
        if (in_array($this->legacyRoleValue(), [UserRole::Admin->value, config('filament-shield.super_admin.name', 'super_admin')], true)) {
            return true;
        }

        return $this->hasSuperAdminRole();
    }

    public function isPlatformSuperAdmin(): bool
    {
        return $this->legacyRoleValue() === config('filament-shield.super_admin.name', 'super_admin')
            || $this->hasSuperAdminRole();
    }

    public function hasSuperAdminRole(): bool
    {
        return DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->join(config('permission.table_names.roles', 'roles'), 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $this->getMorphClass())
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('roles.name', config('filament-shield.super_admin.name', 'super_admin'))
            ->exists();
    }

    public function isLegacyAdmin(): bool
    {
        return $this->legacyRoleValue() === UserRole::Admin->value;
    }

    public function isCompanyAdmin(?int $companyId = null): bool
    {
        $currentTeamId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if ($companyId !== null && function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($companyId);
            $this->unsetRelation('roles');
        }

        $isCompanyAdmin = $this->hasRole('company_admin');

        if ($companyId !== null && function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($currentTeamId);
            $this->unsetRelation('roles');
        }

        return $isCompanyAdmin;
    }

    public function legacyRoleValue(): ?string
    {
        $role = $this->getAttributeFromArray('role');

        return $role instanceof UserRole ? $role->value : ($role === null ? null : (string) $role);
    }
}
