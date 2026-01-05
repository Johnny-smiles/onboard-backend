<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'client_id',
        'google2fa_secret',
        'google2fa_enabled',
        'two_factor_recovery_codes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'google2fa_enabled' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function managedClients()
    {
        return $this->belongsToMany(Client::class, 'admin_client', 'admin_id', 'client_id');
    }

    public function scopeAdmins($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery
                ->where('role', 'admin')
                ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'admin'));
        });
    }
}
