<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShotRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'description',
        'steps',
    ];

    protected $casts = [
        'steps' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
