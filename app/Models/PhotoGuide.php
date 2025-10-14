<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class PhotoGuide extends Model
{
    use HasFactory;
    protected $fillable = ['title','description','example_image_url','tags'];
}
