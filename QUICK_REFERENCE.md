# Quick Reference - Common Commands

## Development Commands

### Start the Application
```bash
# Start Laravel server
php artisan serve
# Visit: http://localhost:8000/portal

# Start queue worker (keep in separate terminal)
php artisan queue:work

# Watch frontend changes (development)
npm run dev
```

### Build & Cache
```bash
# Build frontend for production
npm run build

# Clear all caches
php artisan optimize:clear

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Database
```bash
# Run migrations
php artisan migrate

# Check migration status
php artisan migrate:status

# Seed database
php artisan db:seed

# Reset database (CAREFUL!)
php artisan migrate:fresh --seed
```

### Queue & Jobs
```bash
# Process one job and stop
php artisan queue:work --once

# Keep processing jobs
php artisan queue:work

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Scheduler
```bash
# Run scheduler manually (for testing)
php artisan schedule:run

# List scheduled commands
php artisan schedule:list
```

### Testing OAuth & Publishing
```bash
# Open Laravel Tinker
php artisan tinker

# Check integrations
>>> \App\Models\SocialIntegration::all();

# Queue a photo for publishing
>>> $photo = \App\Models\Photo::first();
>>> $service = app(\App\Services\PublishService::class);
>>> $service->queue([$photo->id], 'meta');

# Process immediately
>>> $service->dispatchDue();

# Manually refresh tokens
>>> \App\Jobs\RefreshSocialTokens::dispatch();

# Exit
>>> exit
```

### Logs
```bash
# Watch Laravel logs
tail -f storage/logs/laravel.log

# Clear logs
echo "" > storage/logs/laravel.log

# Watch queue worker logs (if using Supervisor)
sudo tail -f /var/log/supervisor/onboard-worker-*.log
```

### Routes
```bash
# List all routes
php artisan route:list

# Filter routes
php artisan route:list --path=oauth
php artisan route:list --path=publish
php artisan route:list --path=integrations

# Show route details
php artisan route:list --columns=method,uri,name,action
```

## Database Queries

### Check Integrations
```sql
-- View all integrations
SELECT id, client_id, provider, account_name, status, expires_at
FROM social_integrations;

-- Check active integrations
SELECT * FROM social_integrations WHERE status='active';

-- Check expiring tokens
SELECT provider, expires_at
FROM social_integrations
WHERE expires_at < DATE_ADD(NOW(), INTERVAL 1 DAY);
```

### Check Publications
```sql
-- View all publications
SELECT id, photo_id, service, status, scheduled_at, published_at
FROM photo_publications
ORDER BY created_at DESC;

-- Check success rate
SELECT
    service,
    COUNT(*) as total,
    SUM(CASE WHEN status='published' THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed
FROM photo_publications
GROUP BY service;

-- Check queued items
SELECT * FROM photo_publications WHERE status='queued';

-- Check failed items
SELECT * FROM photo_publications WHERE status='failed';
```

### Check Queue
```sql
-- Pending jobs
SELECT * FROM jobs;

-- Failed jobs
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
```

## Production Commands

### Deployment
```bash
# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Run migrations
php artisan migrate --force

# Optimize
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
sudo supervisorctl restart onboard-worker:*
```

### Monitoring
```bash
# Check queue workers
sudo supervisorctl status onboard-worker:*

# Restart queue workers
sudo supervisorctl restart onboard-worker:*

# Check disk space
df -h

# Check MySQL
systemctl status mysql

# Check Nginx
systemctl status nginx
```

## Environment Variables Quick Reference

```env
# Application
APP_NAME="On Brand"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=base64:...

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=onboard_backend
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Meta (Facebook/Instagram)
META_CLIENT_ID=your_app_id
META_CLIENT_SECRET=your_app_secret
META_REDIRECT_URI="${APP_URL}/api/oauth/callback/meta"

# Google Business Profile
GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_REDIRECT_URI="${APP_URL}/api/oauth/callback/google"

# File Storage (S3 for production)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket

# Queue (Redis for production)
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Mail
MAIL_MAILER=postmark
POSTMARK_TOKEN=your_token
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
```

## API Endpoints

### OAuth
```
GET  /api/v1/integrations/{provider}/redirect?client_id=1
GET  /api/oauth/callback/{provider}
```

### Integrations
```
GET    /api/v1/clients/{client}/integrations
POST   /api/v1/clients/{client}/integrations
DELETE /api/v1/clients/{client}/integrations/{integration}
```

### Publishing
```
POST /api/v1/publish/meta
POST /api/v1/publish/gbp
POST /api/v1/publish/wordpress
POST /api/v1/publish/process-due
```

## Troubleshooting

### OAuth Not Working
```bash
# Clear config cache
php artisan config:clear

# Verify routes
php artisan route:list --path=oauth

# Check .env
cat .env | grep -E "(META|GOOGLE)"

# Check logs
tail -f storage/logs/laravel.log
```

### Queue Not Processing
```bash
# Check if worker is running
ps aux | grep "queue:work"

# Restart worker
killall -9 php
php artisan queue:work &

# Or with Supervisor
sudo supervisorctl status
sudo supervisorctl restart onboard-worker:*
```

### Publishing Fails
```bash
# Check integration status
php artisan tinker
>>> \App\Models\SocialIntegration::where('status', '!=', 'active')->get();

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Check logs
tail -f storage/logs/laravel.log
```

### Token Expired
```bash
php artisan tinker
>>> $integration = \App\Models\SocialIntegration::find(1);
>>> $service = app(\App\Services\SocialIntegrationService::class);
>>> $service->refreshIfNeeded($integration);
>>> $integration->fresh();
```

## Quick Links

- **Facebook Developers**: https://developers.facebook.com
- **Google Cloud Console**: https://console.cloud.google.com
- **Laravel Docs**: https://laravel.com/docs
- **Socialite Docs**: https://laravel.com/docs/socialite

## Default Login

**Admin:**
- Email: admin@example.com
- Password: password

**Portal URL:** http://localhost:8000/portal
