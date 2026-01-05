# Security Considerations & Improvements

This document covers critical security measures and feature improvements for On Brand.

---

## 🔒 CRITICAL SECURITY ISSUES

### 1. Rate Limiting (HIGH PRIORITY - Not Implemented)

**Current Status:** ⚠️ No rate limiting on API endpoints

**Risk:** API abuse, DDoS attacks, credential stuffing

**Implementation:**

```php
// app/Http/Kernel.php - Add to $middlewareGroups
'api' => [
    'throttle:60,1', // 60 requests per minute
],

// For auth endpoints, be more restrictive
Route::middleware('throttle:5,1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// For OAuth redirects (prevent token enumeration)
Route::middleware('throttle:10,1')->group(function () {
    Route::get('integrations/{provider}/redirect', [OAuthController::class, 'redirect']);
});
```

**Action Required:** ✅ Implement immediately before launch

---

### 2. File Upload Security (HIGH PRIORITY - Partially Implemented)

**Current Risks:**
- No file size validation beyond PHP limits
- No MIME type verification
- No malware scanning
- EXIF data (GPS, personal info) not stripped
- Filename not sanitized

**Implementation:**

```php
// app/Http/Controllers/Api/PhotoController.php

public function store(Request $request)
{
    $request->validate([
        'file' => [
            'required',
            'file',
            'mimes:jpeg,jpg,png,webp', // Restrict types
            'max:10240', // 10MB max
            'dimensions:max_width=8000,max_height=8000',
        ],
    ]);

    $file = $request->file('file');

    // Sanitize filename
    $originalName = $file->getClientOriginalName();
    $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '', $originalName);
    $filename = uniqid() . '_' . $safeName;

    // Strip EXIF data (privacy!)
    $this->stripExifData($file);

    // Optional: Virus scan
    // if (!$this->scanForMalware($file)) {
    //     abort(422, 'File failed security scan');
    // }

    $path = $file->storeAs('photos', $filename, 'public');

    // Create photo record...
}

private function stripExifData($file)
{
    $img = imagecreatefromjpeg($file->getRealPath());
    imagejpeg($img, $file->getRealPath(), 100);
    imagedestroy($img);
}
```

**Packages to Consider:**
```bash
# EXIF stripping
composer require intervention/image

# Virus scanning (if you have ClamAV)
composer require xenolope/quahog
```

**Action Required:** ✅ Implement ASAP

---

### 3. SQL Injection Protection (✅ Mostly Covered)

**Status:** Laravel Eloquent provides protection, but watch for:

**Dangerous Patterns to Avoid:**
```php
// ❌ NEVER DO THIS
DB::select("SELECT * FROM users WHERE email = '" . $request->email . "'");
$users = DB::table('users')->whereRaw("email = '" . $request->email . "'");

// ✅ ALWAYS DO THIS
DB::select("SELECT * FROM users WHERE email = ?", [$request->email]);
User::where('email', $request->email)->get();
```

**Action Required:** ✅ Code review to verify all queries use parameter binding

---

### 4. XSS Protection (⚠️ Partially Covered)

**Current Status:** Blade templates auto-escape, but Vue components need review

**Risks in Vue:**
- `v-html` directive can inject malicious scripts
- User-generated captions could contain XSS

**Implementation:**

```javascript
// resources/js/portal/utils/sanitize.ts
import DOMPurify from 'dompurify';

export function sanitizeHtml(dirty: string): string {
    return DOMPurify.sanitize(dirty, {
        ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'a'],
        ALLOWED_ATTR: ['href']
    });
}
```

```bash
npm install dompurify
npm install --save-dev @types/dompurify
```

**In Vue Components:**
```vue
<!-- ❌ DANGEROUS -->
<div v-html="photo.caption"></div>

<!-- ✅ SAFE -->
<div v-html="sanitize(photo.caption)"></div>
<!-- OR BETTER: -->
<div>{{ photo.caption }}</div>
```

**Action Required:** ✅ Audit all Vue components for XSS vulnerabilities

---

### 5. CSRF Protection (✅ Built-in, but verify)

**Status:** Laravel has CSRF protection built-in

**Verify:**
```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    // Make sure OAuth callbacks are here if needed
    'api/oauth/callback/*',
];
```

**For API (Sanctum):** Already protected by token authentication

**Action Required:** ✅ Verify CSRF exclusions are minimal

---

### 6. Authentication & Authorization Vulnerabilities

**Current Issues:**

1. **No 2FA for Admin Accounts**
   ```bash
   composer require pragmarx/google2fa-laravel
   ```

2. **Password Policy Not Enforced**
   ```php
   // app/Http/Controllers/Api/AuthController.php
   $request->validate([
       'password' => [
           'required',
           'min:12',
           'regex:/[a-z]/',      // lowercase
           'regex:/[A-Z]/',      // uppercase
           'regex:/[0-9]/',      // number
           'regex:/[@$!%*#?&]/', // special char
       ],
   ]);
   ```

3. **No Account Lockout After Failed Attempts**
   ```php
   // Use Laravel's built-in throttling
   use Illuminate\Support\Facades\RateLimiter;

   if (RateLimiter::tooManyAttempts('login:'.$request->ip(), 5)) {
       abort(429, 'Too many login attempts');
   }
   ```

4. **Session Fixation Prevention**
   ```php
   // After successful login
   $request->session()->regenerate();
   ```

**Action Required:** ✅ Implement before production launch

---

### 7. API Token Security

**Current Issues:**
- Tokens never expire (Sanctum default)
- No token rotation
- Tokens stored in localStorage (XSS vulnerable)

**Improvements:**

```php
// app/Http/Controllers/Api/AuthController.php
public function login(Request $request)
{
    // ... authentication ...

    $token = $user->createToken('api', ['*'], now()->addDays(30));

    return response()->json([
        'token' => $token->plainTextToken,
        'expires_at' => now()->addDays(30),
    ]);
}
```

**Frontend - Use httpOnly cookies instead:**
```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
))),
```

**Action Required:** ✅ Implement token expiration

---

### 8. Social Media Token Security

**Current Status:** ✅ Tokens encrypted at rest

**Additional Measures:**

1. **Token Access Logging**
   ```php
   // Every time a token is decrypted, log it
   Log::channel('security')->info('Social token accessed', [
       'integration_id' => $integration->id,
       'provider' => $integration->provider,
       'user_id' => auth()->id(),
       'action' => 'publish',
   ]);
   ```

2. **Token Rotation Schedule**
   - Meta tokens: Refresh every 30 days (even if not expired)
   - Google tokens: Verify refresh token still works weekly

3. **Revocation Detection**
   ```php
   // In PublishService, catch revoked token errors
   try {
       $this->publishToMeta(...);
   } catch (OAuthException $e) {
       if ($e->getCode() === 190) { // Token invalid
           $integration->update([
               'status' => 'error',
               'error_message' => 'Token revoked by user',
           ]);

           // Notify admin
           $this->notifyAdminTokenRevoked($integration);
       }
   }
   ```

**Action Required:** ✅ Implement logging and revocation handling

---

### 9. Environment Variable Security

**Current Risks:**
- `.env` file readable if web server misconfigured
- Credentials in version control (if someone commits .env)

**Best Practices:**

```bash
# .gitignore (verify this exists)
.env
.env.backup
.env.production

# File permissions
chmod 600 .env

# Server configuration (Nginx)
location ~ /\.env {
    deny all;
    return 404;
}
```

**Production Secrets Management:**
```bash
# Consider using AWS Secrets Manager or similar
composer require aws/aws-sdk-php

# Or encrypted .env
php artisan env:encrypt --env=production
```

**Action Required:** ✅ Verify .env permissions and gitignore

---

### 10. Database Security

**Issues:**

1. **No Row-Level Security**
   ```php
   // ALWAYS scope queries by user/client
   // ❌ BAD
   Photo::find($id);

   // ✅ GOOD
   Photo::where('client_id', auth()->user()->client_id)->findOrFail($id);
   ```

2. **Database Credentials Rotation**
   - Set reminder to rotate DB password quarterly
   - Use different credentials for read replicas

3. **Backup Encryption**
   ```bash
   # Encrypt backups
   mysqldump ... | gpg --encrypt --recipient admin@yourcompany.com > backup.sql.gpg
   ```

**Action Required:** ✅ Implement query scoping everywhere

---

### 11. Photo Storage Security

**Current Issues:**
- Photos publicly accessible if using S3 public bucket
- No signed URLs for temporary access
- No access logging

**Implementation:**

```php
// config/filesystems.php
's3' => [
    'driver' => 's3',
    'visibility' => 'private', // ← Important!
    // ...
],

// Generate signed URLs
public function getPhotoUrl(Photo $photo): string
{
    return Storage::disk('s3')->temporaryUrl(
        $photo->file_path,
        now()->addMinutes(5) // URL expires in 5 minutes
    );
}
```

**Action Required:** ✅ Make S3 bucket private, use signed URLs

---

### 12. Logging & Monitoring Security

**What to Log (Security Events):**

```php
// app/Http/Middleware/SecurityEventLogger.php
Log::channel('security')->info('Login attempt', [
    'email' => $request->email,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'success' => $success,
]);

Log::channel('security')->warning('OAuth connection attempt', [
    'provider' => $provider,
    'client_id' => $clientId,
    'user_id' => auth()->id(),
    'ip' => $request->ip(),
]);

Log::channel('security')->warning('Photo deleted', [
    'photo_id' => $photo->id,
    'deleted_by' => auth()->id(),
    'client_id' => $photo->client_id,
]);
```

**Configure Separate Security Log:**
```php
// config/logging.php
'channels' => [
    'security' => [
        'driver' => 'daily',
        'path' => storage_path('logs/security.log'),
        'level' => 'info',
        'days' => 90, // Keep security logs longer
    ],
],
```

**Action Required:** ✅ Implement security event logging

---

### 13. Headers & Security Policies

**Add Security Headers:**

```php
// app/Http/Middleware/SecurityHeaders.php
public function handle($request, Closure $next)
{
    $response = $next($request);

    $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-XSS-Protection', '1; mode=block');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

    // Content Security Policy
    $response->headers->set('Content-Security-Policy',
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self' data: https:; " .
        "font-src 'self' data:;"
    );

    return $response;
}
```

**Register Middleware:**
```php
// app/Http/Kernel.php
protected $middleware = [
    \App\Http\Middleware\SecurityHeaders::class,
];
```

**Action Required:** ✅ Implement security headers

---

### 14. Webhook Security (If Implementing)

**For receiving webhooks from social platforms:**

```php
public function handleWebhook(Request $request, string $provider)
{
    // 1. Verify signature
    if (!$this->verifyWebhookSignature($request, $provider)) {
        abort(401, 'Invalid signature');
    }

    // 2. Validate payload
    $payload = $request->json()->all();

    // 3. Process idempotently (prevent duplicate processing)
    $eventId = $payload['id'] ?? null;
    if (Cache::has("webhook:{$eventId}")) {
        return response()->json(['status' => 'already_processed']);
    }

    Cache::put("webhook:{$eventId}", true, 3600);

    // Process webhook...
}

private function verifyWebhookSignature(Request $request, string $provider): bool
{
    if ($provider === 'meta') {
        $signature = $request->header('X-Hub-Signature-256');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), env('META_WEBHOOK_SECRET'));
        return hash_equals($expected, $signature);
    }

    return false;
}
```

**Action Required:** ✅ Implement if using webhooks

---

## 🚀 FEATURE IMPROVEMENTS

### Priority 1 - Essential Features

#### 1. User Management Dashboard

**What's Missing:**
- Can't invite new users
- Can't manage user permissions
- No user activity tracking

**Implementation:**

```php
// User invitation system
php artisan make:controller Api/UserInvitationController

public function invite(Request $request)
{
    $request->validate([
        'email' => 'required|email|unique:users',
        'role' => 'required|in:admin,client',
        'client_id' => 'required_if:role,client',
    ]);

    $invitation = UserInvitation::create([
        'email' => $request->email,
        'role' => $request->role,
        'client_id' => $request->client_id,
        'token' => Str::random(32),
        'expires_at' => now()->addDays(7),
    ]);

    Mail::to($request->email)->send(new UserInvitationMail($invitation));
}
```

---

#### 2. Analytics Dashboard

**What's Missing:**
- No visibility into publishing success rates
- No performance metrics
- No usage statistics

**Needed Metrics:**
- Photos uploaded per day/week/month
- Publishing success rate by platform
- Average time from upload to publish
- Client activity levels
- Token refresh success rates
- Failed job statistics

**Quick Implementation:**

```php
// app/Http/Controllers/Api/AnalyticsController.php
public function dashboard()
{
    return [
        'photos' => [
            'total' => Photo::count(),
            'pending' => Photo::where('approved', false)->count(),
            'approved_today' => Photo::where('approved', true)
                ->whereDate('updated_at', today())->count(),
        ],
        'publications' => [
            'total' => PhotoPublication::count(),
            'success_rate' => DB::table('photo_publications')
                ->selectRaw('
                    SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) / COUNT(*) * 100 as rate
                ')->value('rate'),
            'by_platform' => PhotoPublication::select('service')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) as published')
                ->groupBy('service')
                ->get(),
        ],
        'integrations' => [
            'active' => SocialIntegration::where('status', 'active')->count(),
            'errors' => SocialIntegration::where('status', 'error')->count(),
            'expiring_soon' => SocialIntegration::where('expires_at', '<=', now()->addDays(7))->count(),
        ],
    ];
}
```

---

#### 3. Audit Log / Activity Feed

**What's Missing:**
- No record of who did what when
- Can't track changes to integrations
- No accountability trail

**Implementation:**

```php
// Already have Spatie Activity Log installed!
// Just need to use it

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Photo extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['caption', 'approved', 'review_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}

// In controllers
activity()
    ->performedOn($photo)
    ->causedBy(auth()->user())
    ->withProperties(['old_status' => $oldStatus, 'new_status' => 'approved'])
    ->log('Photo approved');

// View activity
Activity::forSubject($client)
    ->with('causer')
    ->latest()
    ->get();
```

---

#### 4. Notification System

**What's Missing:**
- No in-app notifications
- Email notifications not configured
- No SMS for critical events

**Implementation:**

```php
// app/Notifications/PhotoApproved.php
class PhotoApproved extends Notification
{
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Photo Approved')
            ->line('Your photo has been approved!')
            ->action('View Photo', url('/portal/client/library'));
    }

    public function toArray($notifiable)
    {
        return [
            'photo_id' => $this->photo->id,
            'message' => 'Your photo has been approved',
        ];
    }
}

// Send notification
$user->notify(new PhotoApproved($photo));
```

---

### Priority 2 - Nice to Have

#### 5. Bulk Operations

Already partially implemented, but add:
- Bulk scheduling (publish multiple photos at once)
- Bulk caption editing
- Bulk tagging
- Bulk download

#### 6. Caption Templates

```php
// Migration
Schema::create('caption_templates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('template'); // "Check out our latest {service} project! {hashtags}"
    $table->json('variables')->nullable(); // ['service', 'hashtags']
    $table->timestamps();
});
```

#### 7. Hashtag Management

```php
// Track popular hashtags
Schema::create('hashtags', function (Blueprint $table) {
    $table->id();
    $table->string('tag')->unique();
    $table->integer('usage_count')->default(0);
    $table->timestamps();
});

// Associate with photos
Schema::create('photo_hashtag', function (Blueprint $table) {
    $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
    $table->foreignId('hashtag_id')->constrained()->cascadeOnDelete();
});
```

#### 8. Scheduled Posts Calendar

```vue
<!-- Calendar view for scheduled publications -->
<FullCalendar
    :options="{
        plugins: [dayGridPlugin, interactionPlugin],
        events: scheduledPosts,
        eventClick: handleEventClick,
        editable: true,
        eventDrop: handleReschedule,
    }"
/>
```

---

## 🔧 OPERATIONAL IMPROVEMENTS

### 1. Health Check Endpoint

```php
// routes/api.php
Route::get('health', function() {
    return [
        'status' => 'ok',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'cache' => Cache::has('health_check') || Cache::put('health_check', true, 10),
        'queue' => Queue::size() < 1000, // Warn if queue backing up
        'storage' => Storage::disk('s3')->exists('health-check.txt'),
    ];
});
```

### 2. Error Tracking (Sentry)

```bash
composer require sentry/sentry-laravel

# .env
SENTRY_LARAVEL_DSN=your_sentry_dsn
```

### 3. Performance Monitoring

```bash
composer require laravel/telescope # Development
composer require spatie/laravel-ray # Development debugging
```

### 4. Database Indexing

```php
// Review and add indexes for common queries
Schema::table('photos', function (Blueprint $table) {
    $table->index(['client_id', 'approved']);
    $table->index(['created_at']);
    $table->index(['review_status']);
});

Schema::table('photo_publications', function (Blueprint $table) {
    $table->index(['status', 'scheduled_at']);
    $table->index(['service', 'status']);
});

Schema::table('social_integrations', function (Blueprint $table) {
    $table->index(['status', 'expires_at']);
});
```

### 5. Automated Testing

```php
// tests/Feature/PublishingTest.php
public function test_photo_publishes_to_meta()
{
    $photo = Photo::factory()->create();
    $integration = SocialIntegration::factory()->create([
        'provider' => 'meta',
        'status' => 'active',
    ]);

    $service = app(PublishService::class);
    $service->queue([$photo->id], 'meta');

    Queue::fake();
    $service->dispatchDue();

    $this->assertDatabaseHas('photo_publications', [
        'photo_id' => $photo->id,
        'status' => 'published',
    ]);
}
```

---

## 📋 COMPLIANCE & LEGAL

### 1. GDPR Compliance

**Required:**
- ✅ Privacy Policy
- ✅ Terms of Service
- ✅ Cookie consent
- ✅ Right to deletion
- ✅ Data export capability

```php
// Data export
public function exportData(User $user)
{
    return [
        'user' => $user->only(['name', 'email', 'created_at']),
        'photos' => $user->photos()->get(),
        'activity' => Activity::causedBy($user)->get(),
    ];
}

// Data deletion
public function deleteAccount(User $user)
{
    // Anonymize instead of delete (for audit trail)
    $user->update([
        'name' => 'Deleted User',
        'email' => 'deleted_' . $user->id . '@example.com',
        'deleted_at' => now(),
    ]);

    // Delete photos
    $user->photos()->delete();
}
```

### 2. Terms & Privacy

Create:
- `resources/views/legal/terms.blade.php`
- `resources/views/legal/privacy.blade.php`
- Require acceptance on signup

---

## ✅ IMPLEMENTATION PRIORITY

### Week 1 (Critical Security):
1. ✅ Rate limiting
2. ✅ File upload security
3. ✅ Password policies
4. ✅ Security headers
5. ✅ Token expiration

### Week 2 (Operational):
1. ✅ Health check endpoint
2. ✅ Error tracking (Sentry)
3. ✅ Database indexing
4. ✅ Audit logging
5. ✅ Backup automation

### Week 3 (Features):
1. ✅ Analytics dashboard
2. ✅ User management
3. ✅ Notification system
4. ✅ Activity feed

### Week 4 (Nice to Have):
1. ✅ Caption templates
2. ✅ Hashtag management
3. ✅ Calendar view
4. ✅ Automated tests

---

Would you like me to implement any of these immediately?
