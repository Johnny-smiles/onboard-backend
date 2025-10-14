#!/usr/bin/env bash
# bootstrap_onbrand_full.sh
# One-shot build: Laravel backend + rich portal (client upload, admin review, bulk ops, comments, tags, export, publish stubs)
# This script creates a fresh Laravel app named onbrand-backend and wires everything.
#
# Usage:
#   chmod +x bootstrap_onbrand_full.sh
#   ./bootstrap_onbrand_full.sh
#
set -euo pipefail

PROJECT="onbrand-backend"
APP_URL="http://127.0.0.1:8000"

# ---------- Create Laravel project + deps ----------
if [[ ! -d "${PROJECT}" ]]; then
  echo "==> Creating Laravel project ${PROJECT}"
  composer create-project laravel/laravel "${PROJECT}"
fi
cd "${PROJECT}"

echo "==> Installing PHP deps"
composer require laravel/sanctum laravel/breeze intervention/image

echo "==> Install Breeze (API)"
php artisan breeze:install api

echo "==> NPM deps"
npm i
npm run build

# ---------- .env (SQLite + local) ----------
DB_PATH="$(pwd)/database/database.sqlite"
cat > .env <<EOF
APP_NAME="On Brand"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=${APP_URL}

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=${DB_PATH}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
EOF

echo "==> App key, sqlite, storage link"
php artisan key:generate --ansi
mkdir -p database
touch database/database.sqlite
php artisan storage:link || true

# ---------- Models/Migrations/Controllers base ----------
echo "==> Base models/migrations/controllers"
php artisan make:model Client -m || true
php artisan make:model Project -m || true
php artisan make:model Photo -m || true
php artisan make:model PhotoGuide -m || true
php artisan make:model Reminder -m || true
php artisan make:model Tag -m || true
php artisan make:model Notification -m || true

php artisan make:controller Api/AuthController || true
php artisan make:controller Api/ClientController --api || true
php artisan make:controller Api/ProjectController --api || true
php artisan make:controller Api/PhotoController --api || true
php artisan make:controller Api/PhotoGuideController --api || true
php artisan make:controller Api/ReminderController --api || true
php artisan make:controller Api/NotificationController --api || true

mkdir -p app/Services app/Notifications app/Jobs

# ---------- Overwrite migrations (base) ----------
echo "==> Writing base migrations"
# users
USER_MIG="$(ls database/migrations/*_create_users_table.php | head -n1)"
cat > "${USER_MIG}" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin','client'])->default('client');
            $table->foreignId('client_id')->nullable()->constrained()->onDelete('cascade');
            $table->rememberToken();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};
PHP

# clients
CLIENT_MIG="$(ls database/migrations/*_create_clients_table.php 2>/dev/null || true)"
[[ -z "${CLIENT_MIG}" ]] && CLIENT_MIG="database/migrations/2025_01_01_000001_create_clients_table.php"
cat > "${CLIENT_MIG}" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('brand_color')->nullable();
            $table->boolean('watermark_enabled')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('clients'); }
};
PHP

# projects
PROJECT_MIG="$(ls database/migrations/*_create_projects_table.php 2>/dev/null || true)"
[[ -z "${PROJECT_MIG}" ]] && PROJECT_MIG="database/migrations/2025_01_01_000002_create_projects_table.php"
cat > "${PROJECT_MIG}" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('projects'); }
};
PHP

# photos
PHOTO_MIG="$(ls database/migrations/*_create_photos_table.php 2>/dev/null || true)"
[[ -z "${PHOTO_MIG}" ]] && PHOTO_MIG="database/migrations/2025_01_01_000003_create_photos_table.php"
cat > "${PHOTO_MIG}" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->text('caption')->nullable();
            $table->decimal('gps_lat', 10, 8)->nullable();
            $table->decimal('gps_lng', 11, 8)->nullable();
            $table->unsignedTinyInteger('quality_score')->nullable();
            $table->boolean('approved')->default(false);
            $table->json('edited_variants')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('photos'); }
};
PHP

# photo_guides
GUIDE_MIG="$(ls database/migrations/*_create_photo_guides_table.php 2>/dev/null || true)"
[[ -z "${GUIDE_MIG}" ]] && GUIDE_MIG="database/migrations/2025_01_01_000004_create_photo_guides_table.php"
cat > "${GUIDE_MIG}" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('photo_guides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('example_image_url')->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('photo_guides'); }
};
PHP

# reminders
REM_MIG="$(ls database/migrations/*_create_reminders_table.php 2>/dev/null || true)"
[[ -z "${REM_MIG}" ]] && REM_MIG="database/migrations/2025_01_01_000005_create_reminders_table.php"
cat > "${REM_MIG}" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('photo_guide_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message');
            $table->dateTime('schedule_time');
            $table->enum('repeat', ['none','daily','weekly','monthly'])->default('none');
            $table->enum('status', ['pending','sent','completed'])->default('pending');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reminders'); }
};
PHP

# tags + pivot
cat > database/migrations/2025_01_01_000006_create_tags_and_photo_tag_tables.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('photo_tag', function (Blueprint $table) {
            $table->foreignId('photo_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->primary(['photo_id','tag_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('photo_tag'); Schema::dropIfExists('tags'); }
};
PHP

# notifications
NOTIF_MIG="$(ls database/migrations/*_create_notifications_table.php 2>/dev/null || true)"
[[ -z "${NOTIF_MIG}" ]] && NOTIF_MIG="database/migrations/2025_01_01_000007_create_notifications_table.php"
cat > "${NOTIF_MIG}" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type')->nullable();
            $table->string('title');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('notifications'); }
};
PHP

# photo_comments
cat > database/migrations/2025_01_01_000008_create_photo_comments_table.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('photo_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('body');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('photo_comments'); }
};
PHP

# photo_publications
cat > database/migrations/2025_01_01_000009_create_photo_publications_table.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('photo_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained()->onDelete('cascade');
            $table->string('service'); // wordpress|meta|gbp
            $table->enum('status', ['queued','published','failed'])->default('queued');
            $table->json('payload')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('photo_publications'); }
};
PHP

# ---------- Models ----------
echo "==> Writing models"
cat > app/Models/User.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = ['name','email','password','role','client_id'];
    protected $hidden = ['password','remember_token'];
    public function client() { return $this->belongsTo(Client::class); }
    public function photos() { return $this->hasMany(Photo::class); }
    public function scopeAdmins($q) { return $q->where('role','admin'); }
}
PHP

cat > app/Models/Client.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Client extends Model
{
    use HasFactory;
    protected $fillable = ['name','contact_email','contact_phone','logo_url','brand_color','watermark_enabled','notes'];
    public function users() { return $this->hasMany(User::class); }
    public function projects() { return $this->hasMany(Project::class); }
    public function photos() { return $this->hasMany(Photo::class); }
    public function reminders() { return $this->hasMany(Reminder::class); }
}
PHP

cat > app/Models/Project.php <<'PHP'
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
PHP

cat > app/Models/Photo.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Photo extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id','client_id','project_id','file_path','caption',
        'gps_lat','gps_lng','quality_score','approved','edited_variants','notes'
    ];
    protected $casts = ['edited_variants'=>'array'];
    public function client() { return $this->belongsTo(Client::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function tags() { return $this->belongsToMany(Tag::class, 'photo_tag'); }
    public function comments() { return $this->hasMany(PhotoComment::class); }
    public function publications() { return $this->hasMany(PhotoPublication::class); }
}
PHP

cat > app/Models/PhotoGuide.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class PhotoGuide extends Model
{
    use HasFactory;
    protected $fillable = ['title','description','example_image_url','tags'];
}
PHP

cat > app/Models/Reminder.php <<'PHP'
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
PHP

cat > app/Models/Tag.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Tag extends Model
{
    use HasFactory;
    protected $fillable = ['name','slug'];
    public function photos() { return $this->belongsToMany(Photo::class, 'photo_tag'); }
}
PHP

cat > app/Models/Notification.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Notification extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','type','title','message','read_at'];
    public function user() { return $this->belongsTo(User::class); }
}
PHP

cat > app/Models/PhotoComment.php <<'PHP'
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
PHP

cat > app/Models/PhotoPublication.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class PhotoPublication extends Model
{
    use HasFactory;
    protected $fillable = ['photo_id','service','status','payload','scheduled_at','published_at','error'];
    protected $casts = ['payload'=>'array','scheduled_at'=>'datetime','published_at'=>'datetime'];
    public function photo() { return $this->belongsTo(Photo::class); }
}
PHP

# ---------- Notifications ----------
cat > app/Notifications/NewPhotoUploaded.php <<'PHP'
<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
class NewPhotoUploaded extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public $photo) {}
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable): array
    {
        return [
            'type' => 'photo_upload',
            'title' => 'New photo uploaded',
            'message' => "A new photo was uploaded by {$this->photo->user->name}",
            'photo_id' => $this->photo->id
        ];
    }
}
PHP

cat > app/Notifications/ReminderNotification.php <<'PHP'
<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\Reminder;
class ReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public Reminder $reminder) {}
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable): array
    {
        return [
            'type' => 'reminder',
            'title' => 'Photo Reminder',
            'message' => $this->reminder->message,
            'schedule_time' => $this->reminder->schedule_time,
            'client_id' => $this->reminder->client_id,
        ];
    }
}
PHP

# ---------- Services ----------
cat > app/Services/ImageService.php <<'PHP'
<?php
namespace App\Services;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use App\Models\Photo;
use Illuminate\Support\Str;
class ImageService
{
    public function optimizeAndStore($uploadedFile, $clientId): string
    {
        $image = Image::make($uploadedFile);
        $image->resize(2048, null, function ($c) { $c->aspectRatio(); $c->upsize(); });
        $filename = Str::uuid()->toString().'.jpg';
        $path = "photos/clients/{$clientId}/{$filename}";
        Storage::disk(config('filesystems.default'))->put($path, (string) $image->encode('jpg', 85));
        return $path;
    }
    public function scoreImage(Photo $photo): int
    {
        try {
            $raw = Storage::disk(config('filesystems.default'))->get($photo->file_path);
            $img = Image::make($raw)->resize(32, 32);
            $brightness = 0; $n = 0;
            for ($x=0; $x<32; $x++) for ($y=0; $y<32; $y++) {
                $color = $img->pickColor($x,$y,'array');
                $brightness += ($color[0]+$color[1]+$color[2])/3/255; $n++;
            }
            $score = (int) max(0,min(100, round(($brightness/$n)*100)));
        } catch (\Throwable $e) { $score = 0; }
        $photo->update(['quality_score' => $score]);
        return $score;
    }
}
PHP

cat > app/Services/ReminderService.php <<'PHP'
<?php
namespace App\Services;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
class ReminderService
{
    public function dispatchDueReminders(): int
    {
        $now = Carbon::now();
        $due = Reminder::where('status','pending')->where('schedule_time','<=',$now)->get();
        $count = 0;
        foreach ($due as $reminder) {
            $this->sendReminder($reminder);
            $reminder->update(['status' => 'sent']);
            $this->rescheduleIfRepeating($reminder);
            $count++;
        }
        Log::info("ReminderService dispatched {$count} reminders");
        return $count;
    }
    public function sendReminder(Reminder $reminder): void
    {
        $users = User::where('client_id', $reminder->client_id)->get();
        if ($users->isNotEmpty()) Notification::send($users, new ReminderNotification($reminder));
    }
    protected function rescheduleIfRepeating(Reminder $reminder): void
    {
        $next = null;
        if ($reminder->repeat === 'daily')   $next = now()->parse($reminder->schedule_time)->addDay();
        if ($reminder->repeat === 'weekly')  $next = now()->parse($reminder->schedule_time)->addWeek();
        if ($reminder->repeat === 'monthly') $next = now()->parse($reminder->schedule_time)->addMonth();
        if ($next) $reminder->update(['schedule_time' => $next, 'status' => 'pending']);
    }
}
PHP

cat > app/Services/ZipService.php <<'PHP'
<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
class ZipService
{
    public function createFromPaths(array $paths, string $zipName): string
    {
        $dir = storage_path('app/public/exports');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $zipPath = $dir . '/' . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new \RuntimeException('Could not create ZIP');
        }
        foreach ($paths as $p) {
            $content = Storage::disk(config('filesystems.default'))->get($p);
            $zip->addFromString(basename($p), $content);
        }
        $zip->close();
        return 'exports/' . $zipName; // relative to public storage
    }
}
PHP

cat > app/Services/PublishService.php <<'PHP'
<?php
namespace App\Services;
use App\Models\PhotoPublication;
use Carbon\Carbon;
class PublishService
{
    // Stub: enqueue publications, processed later by scheduler
    public function queue(array $photoIds, string $service, ?string $when = null): array
    {
        $scheduled = $when ? Carbon::parse($when) : now();
        $ids = [];
        foreach ($photoIds as $pid) {
            $pub = PhotoPublication::create([
                'photo_id' => $pid,
                'service' => $service,
                'status' => 'queued',
                'scheduled_at' => $scheduled,
                'payload' => ['note' => 'stub: will publish at scheduled time'],
            ]);
            $ids[] = $pub->id;
        }
        return $ids;
    }
    // Called by scheduler to process queued publications
    public function dispatchDue(): int
    {
        $due = PhotoPublication::where('status','queued')->where('scheduled_at','<=', now())->get();
        $count = 0;
        foreach ($due as $pub) {
            // Simulate success
            $pub->update([
                'status' => 'published',
                'published_at' => now(),
                'payload' => array_merge($pub->payload ?? [], ['external_id' => 'stub-' . $pub->id])
            ]);
            $count++;
        }
        return $count;
    }
}
PHP

# ---------- Controllers (API) base + extras ----------
cat > app/Http/Controllers/Api/AuthController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'in:admin,client',
            'client_id' => 'nullable|exists:clients,id'
        ]);
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $token = $user->createToken('api_token')->plainTextToken;
        return response()->json(['user' => $user, 'token' => $token], 201);
    }
    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);
        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials']);
        }
        $token = $user->createToken('api_token')->plainTextToken;
        return response()->json(['user' => $user, 'token' => $token]);
    }
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
PHP

cat > app/Http/Controllers/Api/ClientController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
class ClientController extends Controller
{
    public function index() { return response()->json(Client::withCount(['projects','photos'])->get()); }
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'brand_color' => 'nullable|string',
            'watermark_enabled' => 'boolean',
            'notes' => 'nullable|string'
        ]);
        $client = Client::create($data);
        return response()->json($client, 201);
    }
    public function show(Client $client) { return response()->json($client->load('projects','photos')); }
    public function update(Request $request, Client $client)
    {
        $client->update($request->only(['name','contact_email','contact_phone','logo_url','brand_color','watermark_enabled','notes']));
        return response()->json($client);
    }
    public function destroy(Client $client) { $client->delete(); return response()->json(['message'=>'Client deleted']); }
}
PHP

cat > app/Http/Controllers/Api/ProjectController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $q = Project::query(); if ($request->client_id) $q->where('client_id', $request->client_id);
        return response()->json($q->with('client')->get());
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date'
        ]);
        $project = Project::create($data);
        return response()->json($project, 201);
    }
    public function show(Project $project) { return response()->json($project->load('client','photos')); }
}
PHP

cat > app/Http/Controllers/Api/PhotoController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Notifications\NewPhotoUploaded;
use App\Services\ImageService;
class PhotoController extends Controller
{
    public function index(Request $request)
    {
        $q = Photo::with(['user','client','tags'])->latest();
        if ($request->client_id) $q->where('client_id',$request->client_id);
        if (! is_null($request->approved)) $q->where('approved', filter_var($request->approved, FILTER_VALIDATE_BOOLEAN));
        if ($request->tag) $q->whereHas('tags', fn($t)=>$t->where('slug',$request->tag)->orWhere('name',$request->tag));
        return response()->json($q->paginate(25));
    }
    public function store(Request $request, ImageService $images)
    {
        $data = $request->validate([
            'file' => 'required|image|max:8192',
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'caption' => 'nullable|string',
            'tags' => 'nullable|string'
        ]);
        $path = $images->optimizeAndStore($request->file('file'), $data['client_id']);
        $photo = Photo::create([
            'user_id' => $request->user()->id,
            'client_id' => $data['client_id'],
            'project_id' => $data['project_id'] ?? null,
            'file_path' => $path,
            'caption' => $data['caption'] ?? null,
        ]);
        $images->scoreImage($photo);
        if (!empty($data['tags'])) {
            $tags = array_filter(array_map('trim', explode(',', $data['tags'])));
            foreach ($tags as $t) app(\App\Http\Controllers\Api\PhotoTagController::class)->add($request, $photo->id, $t);
        }
        User::admins()->get()->each(fn($admin) => $admin->notify(new NewPhotoUploaded($photo)));
        return response()->json($photo, 201);
    }
    public function approve(Photo $photo) { $photo->update(['approved' => true]); return response()->json(['message'=>'Photo approved']); }
    public function destroy(Photo $photo)
    {
        Storage::disk(config('filesystems.default'))->delete($photo->file_path);
        $photo->delete();
        return response()->json(['message' => 'Photo deleted']);
    }
}
PHP

# Extra controllers
cat > app/Http/Controllers/Api/PhotoBulkController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;
use App\Services\ZipService;
class PhotoBulkController extends Controller
{
    public function approve(Request $request)
    {
        $data = $request->validate(['photo_ids' => 'required|array', 'photo_ids.*' => 'integer|exists:photos,id']);
        Photo::whereIn('id', $data['photo_ids'])->update(['approved' => true]);
        return response()->json(['updated' => count($data['photo_ids'])]);
    }
    public function delete(Request $request)
    {
        $data = $request->validate(['photo_ids' => 'required|array', 'photo_ids.*' => 'integer|exists:photos,id']);
        Photo::whereIn('id', $data['photo_ids'])->delete();
        return response()->json(['deleted' => count($data['photo_ids'])]);
    }
    public function export(Request $request, ZipService $zip)
    {
        $data = $request->validate(['photo_ids' => 'required|array', 'photo_ids.*' => 'integer|exists:photos,id']);
        $paths = Photo::whereIn('id', $data['photo_ids'])->pluck('file_path')->all();
        $rel = $zip->createFromPaths($paths, 'photos_export_' . time() . '.zip');
        return response()->json(['url' => url('/storage/' . $rel)]);
    }
}
PHP

cat > app/Http/Controllers/Api/PhotoTagController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class PhotoTagController extends Controller
{
    public function add(Request $request, int $photoId, ?string $tagName = null)
    {
        $photo = Photo::findOrFail($photoId);
        $name = $tagName ?? $request->validate(['tag'=>'required|string'])['tag'];
        $slug = Str::slug($name);
        $tag = Tag::firstOrCreate(['slug'=>$slug], ['name'=>$name]);
        $photo->tags()->syncWithoutDetaching([$tag->id]);
        return response()->json(['ok'=>true]);
    }
    public function remove(Request $request, int $photoId)
    {
        $data = $request->validate(['tag'=>'required|string']);
        $photo = Photo::findOrFail($photoId);
        $tag = Tag::where('slug', Str::slug($data['tag']))->orWhere('name',$data['tag'])->first();
        if ($tag) $photo->tags()->detach($tag->id);
        return response()->json(['ok'=>true]);
    }
}
PHP

cat > app/Http/Controllers/Api/PhotoCommentController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoComment;
use Illuminate\Http\Request;
class PhotoCommentController extends Controller
{
    public function index(Photo $photo) { return response()->json($photo->comments()->with('user:id,name')->latest()->get()); }
    public function store(Request $request, Photo $photo)
    {
        $data = $request->validate(['body'=>'required|string']);
        $c = PhotoComment::create(['photo_id'=>$photo->id, 'user_id'=>$request->user()->id, 'body'=>$data['body']]);
        return response()->json($c->load('user:id,name'), 201);
    }
}
PHP

cat > app/Http/Controllers/Api/PublishController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PublishService;
class PublishController extends Controller
{
    public function queueWordpress(Request $request, PublishService $svc)
    {
        $data = $request->validate(['photo_ids'=>'required|array','photo_ids.*'=>'integer','when'=>'nullable|date']);
        $ids = $svc->queue($data['photo_ids'], 'wordpress', $data['when'] ?? null);
        return response()->json(['queued_publications'=>$ids]);
    }
    public function queueMeta(Request $request, PublishService $svc)
    {
        $data = $request->validate(['photo_ids'=>'required|array','photo_ids.*'=>'integer','when'=>'nullable|date']);
        $ids = $svc->queue($data['photo_ids'], 'meta', $data['when'] ?? null);
        return response()->json(['queued_publications'=>$ids]);
    }
    public function queueGBP(Request $request, PublishService $svc)
    {
        $data = $request->validate(['photo_ids'=>'required|array','photo_ids.*'=>'integer','when'=>'nullable|date']);
        $ids = $svc->queue($data['photo_ids'], 'gbp', $data['when'] ?? null);
        return response()->json(['queued_publications'=>$ids]);
    }
    public function processDue(PublishService $svc)
    {
        $n = $svc->dispatchDue();
        return response()->json(['processed'=>$n]);
    }
}
PHP

cat > app/Http/Controllers/Api/CaptionController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;
class CaptionController extends Controller
{
    public function suggest(Photo $photo)
    {
        // Simple deterministic stub caption
        $caption = "On Brand: Client {$photo->client_id} — captured " . $photo->created_at->format('M d, Y') . ".";
        return response()->json(['caption'=>$caption]);
    }
}
PHP

cat > app/Http/Controllers/Api/NotificationController.php <<'PHP'
<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $items = Notification::where('user_id', $request->user()->id)->latest()->limit(50)->get();
        return response()->json($items);
    }
    public function markRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->update(['read_at' => now()]);
        return response()->json(['message' => 'All notifications marked as read']);
    }
}
PHP

# ---------- Routes & Scheduler ----------
cat > routes/api.php <<'PHP'
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController, ClientController, ProjectController, PhotoController,
    PhotoGuideController, ReminderController, NotificationController,
    PhotoBulkController, PhotoTagController, PhotoCommentController,
    PublishController, CaptionController
};
Route::prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('photos', PhotoController::class)->only(['index','store','destroy']);
        Route::post('photos/{photo}/approve', [PhotoController::class, 'approve']);
        // Tags
        Route::post('photos/{photo}/tags', [PhotoTagController::class, 'add']);
        Route::delete('photos/{photo}/tags', [PhotoTagController::class, 'remove']);
        // Comments
        Route::get('photos/{photo}/comments', [PhotoCommentController::class, 'index']);
        Route::post('photos/{photo}/comments', [PhotoCommentController::class, 'store']);
        // Bulk ops
        Route::post('photos/bulk/approve', [PhotoBulkController::class, 'approve']);
        Route::post('photos/bulk/delete', [PhotoBulkController::class, 'delete']);
        Route::post('photos/bulk/export', [PhotoBulkController::class, 'export']);
        // Publishing stubs
        Route::post('publish/wordpress', [PublishController::class, 'queueWordpress']);
        Route::post('publish/meta', [PublishController::class, 'queueMeta']);
        Route::post('publish/gbp', [PublishController::class, 'queueGBP']);
        Route::post('publish/process-due', [PublishController::class, 'processDue']);
        // Captions
        Route::post('photos/{photo}/suggest-caption', [CaptionController::class, 'suggest']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/mark-read', [NotificationController::class, 'markRead']);
    });
});
PHP

cat > app/Console/Kernel.php <<'PHP'
<?php
namespace App\Console;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(fn() => app(\App\Services\ReminderService::class)->dispatchDueReminders())
            ->everyFifteenMinutes()->withoutOverlapping();
        $schedule->call(fn() => app(\App\Services\PublishService::class)->dispatchDue())
            ->everyFiveMinutes()->withoutOverlapping();
    }
}
PHP

# ---------- Portal SPA (Vue 3) with niceties (simplified but functional) ----------
echo "==> Portal SPA"
npm i vue@^3 vue-router@^4 axios
mkdir -p resources/js/portal/resources resources/js/portal/components resources/js/portal/views resources/js/portal/services resources/views

# Vite entry + App shell
cat > resources/js/portal/main.ts <<'TS'
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import Login from './views/Login.vue';
import ClientUpload from './views/ClientUpload.vue';
import ClientLibrary from './views/ClientLibrary.vue';
import AdminReview from './views/AdminReview.vue';
import SettingsIntegrations from './views/SettingsIntegrations.vue';

const routes = [
  { path: '/', redirect: '/login' },
  { path: '/login', component: Login },
  { path: '/client/upload', component: ClientUpload },
  { path: '/client/library', component: ClientLibrary },
  { path: '/admin/review', component: AdminReview },
  { path: '/settings/integrations', component: SettingsIntegrations },
];

const router = createRouter({ history: createWebHistory('/portal'), routes });
createApp(App).use(router).mount('#app');
TS

cat > resources/js/portal/App.vue <<'VUE'
<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="px-4 py-3 border-b bg-white sticky top-0">
      <div class="max-w-6xl mx-auto flex items-center justify-between">
        <h1 class="font-semibold">On Brand — Portal</h1>
        <nav class="space-x-3 text-sm">
          <a href="/portal/client/upload">Client: Upload</a>
          <a href="/portal/client/library">Client: Library</a>
          <a href="/portal/admin/review">Admin: Review</a>
          <a href="/portal/settings/integrations">Settings</a>
        </nav>
      </div>
    </header>
    <main class="max-w-6xl mx-auto p-4">
      <router-view />
    </main>
  </div>
</template>
<script setup lang="ts"></script>
<style>
*{box-sizing:border-box} body{margin:0}
a{color:#0b5fff;text-decoration:none} a:hover{text-decoration:underline}
input,button,select,textarea{font:inherit}
button{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem .9rem;border-radius:.5rem;border:1px solid #0b5fff;background:#0b5fff;color:white;cursor:pointer}
button.secondary{background:white;color:#0b5fff}
.card{background:white;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1rem}
.grid{display:grid;gap:1rem}
.grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.badge{display:inline-block;padding:.15rem .5rem;border-radius:.4rem;border:1px solid #cbd5e1;font-size:.75rem;color:#334155}
input[type="text"],input[type='number'],input[type="email"],input[type="password"],input[type="file"]{width:100%;padding:.6rem .75rem;border:1px solid #e2e8f0;border-radius:.5rem;background:white}
</style>
VUE

# API + Auth services
cat > resources/js/portal/services/api.ts <<'TS'
import axios from 'axios';
const API_BASE = (window as any).__API_BASE_URL__ || 'http://127.0.0.1:8000/api/v1';
const api = axios.create({ baseURL: API_BASE, timeout: 20000 });
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) { (config.headers = config.headers || {} as any).Authorization = `Bearer ${token}`; }
  return config;
});
export default api;
TS

cat > resources/js/portal/services/auth.ts <<'TS'
import api from './api';
export async function login(email:string, password:string) {
  const { data } = await api.post('/login', { email, password });
  if (data?.token) localStorage.setItem('token', data.token);
  localStorage.setItem('user', JSON.stringify(data?.user||{}));
  return data;
}
export function currentUser(){ try{return JSON.parse(localStorage.getItem('user')||'{}')}catch{return{}} }
export function isAdmin(){ return currentUser()?.role === 'admin'; }
export function logout(){ try{api.post('/logout')}catch{} localStorage.removeItem('token'); localStorage.removeItem('user'); }
TS

# Components: Uploader (DnD, multi, resize, concurrency, retry)
cat > resources/js/portal/components/Uploader.vue <<'VUE'
<template>
  <div class="card">
    <h3>Drag & drop photos (or click)</h3>
    <div class="dropzone" @dragover.prevent @drop.prevent="onDrop" @click="pick">
      <p v-if="files.length===0">Drop images here or click to select</p>
      <ul v-else>
        <li v-for="f in files" :key="f.id" style="display:flex;align-items:center;gap:.5rem;margin:.25rem 0">
          <span class="badge">{{ f.file.name }} ({{ Math.round(f.file.size/1024) }} KB)</span>
          <span v-if="f.progress>=0">{{ f.progress }}%</span>
          <span v-if="f.error" style="color:#dc2626">{{ f.error }}</span>
        </li>
      </ul>
    </div>
    <input ref="inp" type="file" accept="image/*" multiple style="display:none" @change="onPick">
    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button :disabled="busy || files.length===0" @click="start">{{ busy ? 'Uploading…' : 'Upload' }}</button>
      <button class="secondary" :disabled="busy" @click="clear">Clear</button>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue';
const emit = defineEmits<{(e:'upload', payload: {blob:Blob, name:string}[]):void}>();
const files = ref<{id:number,file:File,progress:number,error?:string}[]>([]);
const busy = ref(false);
const inp = ref<HTMLInputElement|null>(null);

function pick(){ inp.value?.click(); }
function onPick(e:any){ pushFiles(e.target.files); e.target.value=''; }
function onDrop(e:DragEvent){ if(e.dataTransfer?.files) pushFiles(e.dataTransfer.files); }
let _id=1;
function pushFiles(list: FileList){ for(const f of Array.from(list)){ if(f.type.startsWith('image/')) files.value.push({id:_id++, file:f, progress:0}); } }
function clear(){ files.value=[]; }
async function resizeBlob(file: File, maxW=2048): Promise<Blob> {
  const img = new Image();
  const url = URL.createObjectURL(file);
  await new Promise<void>((res, rej)=>{ img.onload=()=>res(); img.onerror=rej; img.src=url; });
  const scale = Math.min(1, maxW / img.width);
  const w = Math.round(img.width * scale), h = Math.round(img.height * scale);
  const canvas = document.createElement('canvas'); canvas.width=w; canvas.height=h;
  const ctx = canvas.getContext('2d')!; ctx.drawImage(img,0,0,w,h);
  return await new Promise<Blob>((res)=> canvas.toBlob(b=>res(b!), 'image/jpeg', 0.85));
}
async function start(){
  busy.value=true;
  const payload: {blob:Blob,name:string}[] = [];
  for(const f of files.value){
    try{
      const blob = await resizeBlob(f.file);
      payload.push({blob, name: f.file.name.replace(/\.[^.]+$/,'')+'.jpg'});
      f.progress=100;
    }catch(err:any){ f.error = 'Failed to process'; }
  }
  emit('upload', payload);
  busy.value=false;
}
</script>
<style scoped>
.dropzone{border:2px dashed #cbd5e1;border-radius:.75rem;padding:1rem;cursor:pointer;background:#f8fafc;min-height:120px;display:flex;align-items:center;justify-content:center}
</style>
VUE

# PhotoCard + Comments + BulkBar + PublishDrawer (simplified)
cat > resources/js/portal/components/PhotoCard.vue <<'VUE'
<template>
  <div class="card">
    <img :src="fileUrl(photo.file_path)" style="width:100%;border-radius:.5rem" />
    <div style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap">
      <span class="badge">#{{ photo.id }}</span>
      <span class="badge">Client {{ photo.client_id }}</span>
      <span class="badge">Score {{ photo.quality_score ?? '-' }}</span>
      <span class="badge" :style="{borderColor: photo.approved?'#16a34a':'#f59e0b', color: photo.approved?'#166534':'#92400e'}">
        {{ photo.approved ? 'Approved' : 'Pending' }}
      </span>
    </div>
    <div style="margin-top:.25rem">{{ photo.caption || 'No caption' }}</div>
    <div style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap">
      <span v-for="t in (photo.tags || [])" :key="t.id" class="badge">{{ t.name }}</span>
    </div>
    <div style="margin-top:.5rem;display:flex;gap:.5rem">
      <button v-if="!photo.approved" @click="$emit('approve', photo)">Approve</button>
      <button class="secondary" @click="$emit('delete', photo)">Delete</button>
      <button class="secondary" @click="$emit('comment', photo)">Comments</button>
      <button class="secondary" @click="$emit('tag', photo)">Tags</button>
      <button class="secondary" @click="$emit('publish', photo)">Publish</button>
    </div>
  </div>
</template>
<script setup lang="ts">
defineProps<{photo:any}>();
function fileUrl(p:string){ return `/storage/${p.replace(/^public\//,'')}` }
</script>
VUE

cat > resources/js/portal/components/CommentsPanel.vue <<'VUE'
<template>
  <div class="card">
    <h3>Comments</h3>
    <div v-if="items.length===0" class="text-sm" style="color:#64748b">No comments yet.</div>
    <ul>
      <li v-for="c in items" :key="c.id" style="margin:.25rem 0">
        <b>{{ c.user?.name || 'User' }}:</b> {{ c.body }}
      </li>
    </ul>
    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <input v-model="draft" type="text" placeholder="Write a comment…" />
      <button @click="post">Send</button>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
const props = defineProps<{photoId:number}>();
const items = ref<any[]>([]);
const draft = ref('');
async function load(){ const {data} = await api.get(`/photos/${props.photoId}/comments`); items.value = data; }
async function post(){
  if(!draft.value.trim()) return;
  await api.post(`/photos/${props.photoId}/comments`, { body: draft.value.trim() });
  draft.value=''; await load();
}
onMounted(load);
</script>
VUE

cat > resources/js/portal/components/BulkBar.vue <<'VUE'
<template>
  <div class="card" v-if="count>0" style="display:flex;align-items:center;gap:.5rem;justify-content:space-between">
    <div><b>{{ count }}</b> selected</div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <button @click="$emit('approve')">Approve</button>
      <button class="secondary" @click="$emit('delete')">Delete</button>
      <button class="secondary" @click="$emit('export')">Export ZIP</button>
      <input v-model="tag" type="text" placeholder="Add tag" style="max-width:180px">
      <button class="secondary" @click="$emit('tag', tag)">Add Tag</button>
      <button class="secondary" @click="$emit('publish')">Publish…</button>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue';
defineProps<{count:number}>();
const tag = ref('');
</script>
VUE

cat > resources/js/portal/components/PublishDrawer.vue <<'VUE'
<template>
  <div class="card">
    <h3>Publish</h3>
    <label>Service</label>
    <select v-model="service">
      <option value="wordpress">WordPress</option>
      <option value="meta">Meta/Instagram</option>
      <option value="gbp">Google Business Profile</option>
    </select>
    <label class="mt-2">When (optional)</label>
    <input v-model="when" type="datetime-local" />
    <div style="margin-top:.5rem;display:flex;gap:.5rem">
      <button @click="queue">Queue</button>
      <button class="secondary" @click="$emit('close')">Close</button>
    </div>
    <p class="text-sm" style="color:#64748b;margin-top:.5rem">Stubs only — will mark as published once scheduled time hits.</p>
  </div>
</template>
<script setup lang="ts">
import api from '../services/api';
const props = defineProps<{photoIds:number[]}>();
const emit = defineEmits<{(e:'queued'):void,(e:'close'):void}>();
const service = $ref('wordpress');
const when = $ref('');
async function queue(){
  const body:any = { photo_ids: props.photoIds }; if (when) body.when = when;
  const path = service==='wordpress' ? '/publish/wordpress' : service==='meta' ? '/publish/meta' : '/publish/gbp';
  await api.post(path, body);
  emit('queued');
}
</script>
VUE

# Views
cat > resources/js/portal/views/Login.vue <<'VUE'
<template>
  <div class="grid" style="max-width:28rem;margin:4rem auto">
    <div class="card">
      <h2>Sign in</h2>
      <p class="text-sm" style="color:#475569">Use your On Brand account.</p>
      <label>Email</label>
      <input v-model="email" type="email" placeholder="admin@example.com" />
      <label class="mt-2">Password</label>
      <input v-model="password" type="password" placeholder="password" />
      <button class="mt-3" :disabled="busy" @click="doLogin">{{ busy ? 'Signing in…' : 'Sign in' }}</button>
      <p v-if="error" class="text-sm" style="color:#dc2626;margin-top:.75rem">{{ error }}</p>
      <p class="text-xs" style="color:#64748b;margin-top:.75rem">Tip: default demo is admin@example.com / password</p>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../services/auth';
const router = useRouter();
const email = ref('admin@example.com'); const password = ref('password');
const busy = ref(false); const error = ref('');
async function doLogin(){
  busy.value=true; error.value='';
  try{ await login(email.value, password.value); router.replace('/client/upload'); }
  catch(e:any){ error.value = e?.response?.data?.message || 'Login failed'; }
  finally{ busy.value=false; }
}
</script>
VUE

cat > resources/js/portal/views/ClientUpload.vue <<'VUE'
<template>
  <div class="grid">
    <Uploader @upload="onUpload" />
    <div class="card">
      <h3>Quick Tags & Caption (applied to all)</h3>
      <label>Tags (comma separated)</label>
      <input v-model="tags" type="text" placeholder="before, team" />
      <label class="mt-2">Caption</label>
      <input v-model="caption" type="text" placeholder="Describe the photo..." />
      <label class="mt-2">Client ID</label>
      <input v-model.number="clientId" type="number" />
    </div>
    <div v-if="ok" class="card" style="color:#16a34a">Uploaded!</div>
    <div v-if="err" class="card" style="color:#dc2626">{{ err }}</div>
  </div>
</template>
<script setup lang="ts">
import Uploader from '../components/Uploader.vue';
import api from '../services/api';
import { ref } from 'vue';
const tags = ref(''); const caption = ref(''); const clientId = ref<number>(1);
const ok = ref(false); const err = ref('');
async function onUpload(items:{blob:Blob,name:string}[]) {
  ok.value=false; err.value='';
  try{
    for(const it of items){
      const form = new FormData();
      form.append('file', it.blob, it.name);
      form.append('client_id', String(clientId.value));
      if (caption.value) form.append('caption', caption.value);
      if (tags.value) form.append('tags', tags.value);
      await api.post('/photos', form);
    }
    ok.value=true;
  } catch(e:any){ err.value = e?.response?.data?.message || 'Upload failed'; }
}
</script>
VUE

cat > resources/js/portal/views/ClientLibrary.vue <<'VUE'
<template>
  <div class="grid">
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h2>My Library</h2>
        <button class="secondary" @click="load">Refresh</button>
      </div>
      <div class="grid grid-2">
        <div v-for="p in photos" :key="p.id" class="card">
          <img :src="fileUrl(p.file_path)" style="width:100%;border-radius:.5rem" />
          <div style="margin-top:.5rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
            <span class="badge">#{{ p.id }}</span>
            <span class="badge">Approved: {{ p.approved ? 'Yes' : 'No' }}</span>
            <span class="badge">Score {{ p.quality_score ?? '-' }}</span>
          </div>
          <div style="margin-top:.25rem">{{ p.caption || 'No caption' }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
const photos = ref<any[]>([]);
function fileUrl(p:string){ return `/storage/${p.replace(/^public\//,'')}`; }
async function load(){ const {data} = await api.get('/photos'); photos.value = data?.data || data || []; }
onMounted(load);
</script>
VUE

cat > resources/js/portal/views/AdminReview.vue <<'VUE'
<template>
  <div class="grid">
    <BulkBar :count="selected.size" @approve="bulkApprove" @delete="bulkDelete" @export="bulkExport" @tag="bulkTag" @publish="openPublish" />
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h2>Admin Review</h2>
        <div>
          <label>Approved</label>
          <select v-model="approved">
            <option :value="null">All</option>
            <option :value="0">Pending</option>
            <option :value="1">Approved</option>
          </select>
          <button class="secondary" @click="load" style="margin-left:.5rem">Refresh</button>
        </div>
      </div>
      <div class="grid grid-2">
        <div v-for="p in photos" :key="p.id" class="card">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <label><input type="checkbox" :checked="selected.has(p.id)" @change="toggle(p.id)" /> Select</label>
            <button class="secondary" @click="suggest(p)">Suggest caption</button>
          </div>
          <img :src="fileUrl(p.file_path)" style="width:100%;border-radius:.5rem" />
          <div style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap">
            <span class="badge">#{{ p.id }}</span>
            <span class="badge">Client {{ p.client_id }}</span>
            <span class="badge">Score {{ p.quality_score ?? '-' }}</span>
            <span class="badge">Approved: {{ p.approved?'Yes':'No' }}</span>
          </div>
          <div style="margin-top:.25rem">{{ p.caption || 'No caption' }}</div>
          <div style="margin-top:.25rem">
            <input v-model="tagDraft" type="text" placeholder="Add tag…" style="max-width:200px" />
            <button class="secondary" @click="addTag(p)">Add</button>
          </div>
          <div style="margin-top:.25rem;display:flex;gap:.5rem;flex-wrap:wrap">
            <span v-for="t in (p.tags || [])" :key="t.id" class="badge">{{ t.name }}</span>
          </div>
          <div style="margin-top:.5rem;display:flex;gap:.5rem">
            <button v-if="!p.approved" @click="approve(p)">Approve</button>
            <button class="secondary" @click="remove(p)">Delete</button>
            <button class="secondary" @click="openComments(p)">Comments</button>
            <button class="secondary" @click="openPublish([p.id])">Publish…</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showComments" class="card">
      <CommentsPanel :photo-id="activePhotoId" />
    </div>
    <div v-if="showPublish" class="card">
      <PublishDrawer :photo-ids="Array.from(selected)" @close="showPublish=false" @queued="onQueued" />
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import api from '../services/api';
import BulkBar from '../components/BulkBar.vue';
import CommentsPanel from '../components/CommentsPanel.vue';
import PublishDrawer from '../components/PublishDrawer.vue';

const photos = ref<any[]>([]);
const approved = ref<any>(null);
const selected = ref<Set<number>>(new Set());
const tagDraft = ref('');
const showComments = ref(false);
const activePhotoId = ref<number>(0);
const showPublish = ref(false);

function fileUrl(p:string){ return `/storage/${p.replace(/^public\//,'')}`; }
function toggle(id:number){ selected.value.has(id) ? selected.value.delete(id) : selected.value.add(id); selected.value = new Set(selected.value); }

async function load(){
  const params:any = {}; if(approved.value!==null) params.approved = approved.value;
  const {data} = await api.get('/photos', { params });
  photos.value = data?.data || data || [];
  // fill tag arrays if missing
  photos.value.forEach((x:any)=>{ x.tags = x.tags || []; });
}
async function approve(p:any){ await api.post(`/photos/${p.id}/approve`); await load(); }
async function remove(p:any){ await api.delete(`/photos/${p.id}`); await load(); }
async function addTag(p:any){ if(!tagDraft.value.trim()) return; await api.post(`/photos/${p.id}/tags`, { tag: tagDraft.value.trim() }); tagDraft.value=''; await load(); }
async function openComments(p:any){ activePhotoId.value = p.id; showComments.value = true; }
async function suggest(p:any){
  const { data } = await api.post(`/photos/${p.id}/suggest-caption`);
  alert('Suggested: ' + data.caption);
}

async function bulkApprove(){ await api.post('/photos/bulk/approve', { photo_ids: Array.from(selected.value) }); selected.value.clear(); await load(); }
async function bulkDelete(){ await api.post('/photos/bulk/delete', { photo_ids: Array.from(selected.value) }); selected.value.clear(); await load(); }
async function bulkExport(){
  const { data } = await api.post('/photos/bulk/export', { photo_ids: Array.from(selected.value) });
  window.open(data.url, '_blank');
}
function bulkTag(tag:string){ if(!tag.trim()) return; Promise.all(Array.from(selected.value).map(id=> api.post(`/photos/${id}/tags`, { tag }))).then(load); }
function openPublish(photoIds?:number[]){ if(photoIds) selected.value = new Set(photoIds); showPublish.value = true; }
function onQueued(){ showPublish.value=false; alert('Queued! It will auto-publish at the scheduled time.'); }

watch(approved, load);
onMounted(load);
</script>
VUE

cat > resources/js/portal/views/SettingsIntegrations.vue <<'VUE'
<template>
  <div class="card">
    <h2>Integrations (stubs)</h2>
    <p class="text-sm" style="color:#64748b">Store API keys locally for now. Later, we can persist per-tenant on the server.</p>
    <label>WordPress URL</label>
    <input v-model="wpUrl" type="text" placeholder="https://example.com" />
    <label class="mt-2">WordPress Token</label>
    <input v-model="wpToken" type="text" />
    <label class="mt-2">Meta Token</label>
    <input v-model="metaToken" type="text" />
    <label class="mt-2">GBP Token</label>
    <input v-model="gbpToken" type="text" />
    <div style="margin-top:.5rem"><button @click="save">Save</button></div>
    <p v-if="ok" style="color:#16a34a;margin-top:.5rem">Saved</p>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
const wpUrl=ref(''), wpToken=ref(''), metaToken=ref(''), gbpToken=ref(''), ok=ref(false);
function save(){
  localStorage.setItem('onbrand_wp_url', wpUrl.value);
  localStorage.setItem('onbrand_wp_token', wpToken.value);
  localStorage.setItem('onbrand_meta_token', metaToken.value);
  localStorage.setItem('onbrand_gbp_token', gbpToken.value);
  ok.value = true; setTimeout(()=> ok.value=false, 1000);
}
onMounted(()=>{
  wpUrl.value = localStorage.getItem('onbrand_wp_url') || '';
  wpToken.value = localStorage.getItem('onbrand_wp_token') || '';
  metaToken.value = localStorage.getItem('onbrand_meta_token') || '';
  gbpToken.value = localStorage.getItem('onbrand_gbp_token') || '';
});
</script>
VUE

# Portal blade + route + Vite
cat > resources/views/portal.blade.php <<'BLADE'
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>On Brand — Portal</title>
    <script> window.__API_BASE_URL__ = "{{ rtrim(config('app.url'), '/') }}/api/v1"; </script>
    @vite('resources/js/portal/main.ts')
  </head>
  <body><div id="app"></div></body>
</html>
BLADE

if ! grep -q "Route::get('/portal" routes/web.php 2>/dev/null; then
  cat >> routes/web.php <<'PHP'
use Illuminate\Support\Facades\Route;
Route::get('/portal/{any?}', function () { return view('portal'); })->where('any', '.*');
PHP
fi

# Vite config
if [[ ! -f vite.config.ts ]]; then
  cat > vite.config.ts <<'TS'
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/portal/main.ts'],
      refresh: true,
    }),
    vue(),
  ],
});
TS
else
  if ! grep -q "resources/js/portal/main.ts" vite.config.ts; then
    sed -i.bak "s/input: \\\[/input: ['resources\/js\/portal\/main.ts',/g" vite.config.ts || true
  fi
fi

echo "==> Build portal assets"
npm run build

# ---------- Migrate ----------
echo "==> Migrating database"
php artisan migrate

echo "==> DONE!"
echo "Run server: php artisan serve"
echo "Open portal: ${APP_URL}/portal"
echo ""
echo "Tip: seed an admin via Tinker:"
echo "php artisan tinker <<'PHP'"
echo "\\App\\Models\\Client::firstOrCreate(['name'=>'Demo Co']);"
echo "$user = \\App\\Models\\User::create(['name'=>'Admin','email'=>'admin@example.com','password'=>bcrypt('password'),'role'=>'admin','client_id'=>1]);"
echo "echo $user->createToken('api')->plainTextToken.PHP_EOL;"
echo "PHP"
