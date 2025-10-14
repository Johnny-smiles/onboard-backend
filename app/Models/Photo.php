<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Photo extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'client_id',
        'project_id',
        'file_path',
        'caption',
        'gps_lat',
        'gps_lng',
        'quality_score',
        'approved',
        'edited_variants',
        'notes',
    ];

    protected $casts = [
        'edited_variants' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'photo_tag');
    }

    public function comments()
    {
        return $this->hasMany(PhotoComment::class);
    }

    public function publications()
    {
        return $this->hasMany(PhotoPublication::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'client_id',
                'project_id',
                'caption',
                'approved',
                'quality_score',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
