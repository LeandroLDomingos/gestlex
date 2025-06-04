<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Import BelongsToMany

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    /**
     * The roles that belong to the user.
     * This relationship uses the 'role_user' pivot table.
     */
    public function roles(): BelongsToMany
    {
        // Arguments: Related model, pivot table name, foreign pivot key for current model, foreign pivot key for related model
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    /**
     * The direct permissions that belong to the user.
     * This relationship uses the 'permission_user' pivot table.
     * This is the relationship that was likely causing the error if misconfigured.
     */
    public function permissions(): BelongsToMany
    {
        // Arguments: Related model, pivot table name, foreign pivot key for current model, foreign pivot key for related model
        return $this->belongsToMany(Permission::class, 'permission_user', 'user_id', 'permission_id');
    }

    // Para obter as permissões de um utilizador através dos seus papéis:
    public function hasPermissionTo(string $permissionName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->permissions()->where('name', $permissionName)->exists()) {
                return true;
            }
        }
        return false;
    }

    // Ou para obter todas as permissões do utilizador (pode ser otimizado)
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        return $this->roles()->with('permissions:id,name')->get()->pluck('permissions')->flatten()->unique('id');
    }
}
