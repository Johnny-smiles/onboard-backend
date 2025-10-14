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
    ];

    protected $hidden = [
        'password',
        'remember_token',
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
