<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'provider',
        'account_name',
        'external_ids',
        'scopes',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'expires_at',
        'connected_at',
        'status',
    ];

    protected $casts = [
        'external_ids' => 'array',
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
