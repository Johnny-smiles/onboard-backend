<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class PhotoComment extends Model
{
    use HasFactory;
    protected $fillable = ['photo_id','user_id','body'];
    public function photo() { return $this->belongsTo(Photo::class); }
    public function user() { return $this->belongsTo(User::class); }
}
