<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Project extends Model
{
    use HasFactory;
    protected $fillable = ['client_id','name','description','location','start_date','end_date'];
    public function client() { return $this->belongsTo(Client::class); }
    public function photos() { return $this->hasMany(Photo::class); }
}
