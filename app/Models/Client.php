<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Client extends Model
{
    use HasFactory;
    protected $fillable = ['name','contact_email','contact_phone','logo_url','brand_color','watermark_enabled','notes'];
    public function users() { return $this->hasMany(User::class); }
    public function admins() { return $this->belongsToMany(User::class, 'admin_client', 'client_id', 'admin_id'); }
    public function projects() { return $this->hasMany(Project::class); }
    public function photos() { return $this->hasMany(Photo::class); }
    public function reminders() { return $this->hasMany(Reminder::class); }
    public function socialIntegrations() { return $this->hasMany(SocialIntegration::class); }
    public function shotRecipes() { return $this->hasMany(ShotRecipe::class); }
    public function captureReminders() { return $this->hasMany(CaptureReminder::class); }
}
