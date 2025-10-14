<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Reminder extends Model
{
    use HasFactory;
    protected $fillable = ['client_id','photo_guide_id','message','schedule_time','repeat','status'];
    public function client() { return $this->belongsTo(Client::class); }
    public function photoGuide() { return $this->belongsTo(PhotoGuide::class); }
}
