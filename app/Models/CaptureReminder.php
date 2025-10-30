<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CaptureReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'shot_recipe_id',
        'title',
        'message',
        'channel',
        'target',
        'send_at',
        'repeat_interval',
        'last_sent_at',
        'is_active',
    ];

    protected $casts = [
        'send_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function shotRecipe()
    {
        return $this->belongsTo(ShotRecipe::class);
    }

    public function scheduleNextRun(): void
    {
        if (!$this->repeat_interval) {
            $this->is_active = false;
            return;
        }

        $currentSendAt = $this->send_at instanceof Carbon ? $this->send_at : Carbon::parse($this->send_at);

        $this->send_at = match ($this->repeat_interval) {
            'daily' => $currentSendAt->addDay(),
            'weekly' => $currentSendAt->addWeek(),
            'monthly' => $currentSendAt->addMonth(),
            default => null,
        };

        if (!$this->send_at) {
            $this->is_active = false;
        }
    }
}
