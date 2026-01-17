<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoPublication extends Model
{
    use HasFactory;

    protected $fillable = ['photo_id', 'service', 'status', 'payload', 'scheduled_at', 'published_at', 'error'];

    protected $casts = ['payload' => 'array', 'scheduled_at' => 'datetime', 'published_at' => 'datetime'];

    public function photo()
    {
        return $this->belongsTo(Photo::class);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeDue($query)
    {
        return $query->queued()->where('scheduled_at', '<=', now());
    }
}
