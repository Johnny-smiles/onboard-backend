# On Brand - Deployment & Setup Guide

This guide will help you deploy and configure the On Brand application for production.

## Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm
- MySQL 8.0+
- Redis (recommended for queues)
- Supervisor (for queue workers)

## 1. Environment Configuration

### Copy and configure environment variables:

```bash
cp .env.example .env
```

### Update the following in `.env`:

#### Application
```env
APP_NAME="On Brand"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=  # Run: php artisan key:generate
```

#### Database
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=onboard_backend
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

#### File Storage (Use S3 for production)
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
```

#### Queue Configuration
```env
QUEUE_CONNECTION=redis  # or database
```

#### Mail Configuration (Choose your provider)
```env
# Example with Postmark
MAIL_MAILER=postmark
POSTMARK_TOKEN=your_postmark_token
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

#### Social Media Integration

**Meta (Facebook/Instagram):**
1. Go to https://developers.facebook.com
2. Create a new app
3. Add Facebook Login product
4. Configure OAuth redirect: `https://yourdomain.com/api/oauth/callback/meta`
5. Get your credentials:

```env
META_CLIENT_ID=your_facebook_app_id
META_CLIENT_SECRET=your_facebook_app_secret
META_REDIRECT_URI="${APP_URL}/api/oauth/callback/meta"
```

**Google Business Profile:**
1. Go to https://console.cloud.google.com
2. Create a new project
3. Enable Google My Business API
4. Create OAuth 2.0 credentials
5. Add authorized redirect: `https://yourdomain.com/api/oauth/callback/google`

```env
GOOGLE_CLIENT_ID=your_google_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI="${APP_URL}/api/oauth/callback/google"
```

## 2. Installation

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies
npm ci

# Build frontend assets
npm run build

# Generate application key (if not done)
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed database (optional - for demo data)
php artisan db:seed

# Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Link storage (if using local storage)
php artisan storage:link
```

## 3. Queue Worker Setup

Create supervisor configuration at `/etc/supervisor/conf.d/onboard-worker.conf`:

```ini
[program:onboard-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start onboard-worker:*
```

## 4. Scheduler Setup

Add to crontab (`crontab -e`):

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs these jobs automatically:
- **Every 5 minutes**: Process due photo publications
- **Every 5 minutes**: Process capture reminders
- **Every hour**: Refresh social media tokens

## 5. Web Server Configuration

### Nginx Example:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com;
    root /path/to/your/project/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 6. SSL Certificate

Install Let's Encrypt SSL:

```bash
sudo certbot --nginx -d yourdomain.com
```

## 7. Testing the Setup

### Test OAuth Flow:

1. Login as admin: `admin@example.com` / `password`
2. Navigate to Admin → Clients → Select a Client → Social Connections
3. Click "Connect Meta" or "Connect Google"
4. Complete OAuth flow
5. Verify integration is saved in database

### Test Photo Upload & Publishing:

1. Upload a photo as a client user
2. Approve it as admin
3. Click "Publish" and select a platform
4. Check `photo_publications` table for queued item
5. Run: `php artisan queue:work` to process immediately
6. Or wait 5 minutes for scheduler
7. Verify photo appears on social media platform

### Test Token Refresh:

```bash
# Manually trigger token refresh
php artisan tinker
>>> \App\Jobs\RefreshSocialTokens::dispatch();
```

Check logs at `storage/logs/laravel.log` for refresh activity.

## 8. Monitoring & Logs

### Important logs to monitor:

- **Laravel logs**: `storage/logs/laravel.log`
- **Queue worker logs**: `/var/log/supervisor/onboard-worker-*.log`
- **Nginx access/error**: `/var/log/nginx/access.log` and `error.log`

### Set up log rotation:

Create `/etc/logrotate.d/laravel`:

```
/path/to/your/project/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 0644 www-data www-data
}
```

## 9. Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials secured
- [ ] Social API secrets stored safely in `.env`
- [ ] SSL certificate installed and auto-renewing
- [ ] File upload size limits configured
- [ ] CORS settings reviewed
- [ ] Rate limiting configured on API routes
- [ ] Backups scheduled for database and uploaded files

## 10. Backup Strategy

### Database backup script:

```bash
#!/bin/bash
mysqldump -u user -p'password' onboard_backend > backup-$(date +%Y%m%d).sql
# Upload to S3 or backup server
```

### File storage backup:

If using S3, enable versioning on the bucket.
If using local storage, backup the `storage/app/public` directory.

## 11. Common Issues

### Queue not processing:
```bash
# Check supervisor status
sudo supervisorctl status onboard-worker:*

# Restart workers
sudo supervisorctl restart onboard-worker:*
```

### OAuth redirect not working:
- Verify APP_URL matches your domain exactly
- Check META_REDIRECT_URI and GOOGLE_REDIRECT_URI in .env
- Ensure these match what's configured in Facebook/Google consoles

### Photos not uploading:
- Check file permissions on `storage/app/public`
- Verify `storage:link` was run
- Check `upload_max_filesize` and `post_max_size` in php.ini

### Token refresh failing:
- Check integration status in `social_integrations` table
- Verify credentials are still valid in provider console
- Check `storage/logs/laravel.log` for specific errors

## 12. Performance Optimization

```bash
# Enable OPcache in php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2

# Use Redis for cache and sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Queue optimization
QUEUE_CONNECTION=redis
```

## 13. Scaling

As your usage grows:

1. **Database**: Consider read replicas
2. **Queue workers**: Increase `numprocs` in supervisor config
3. **File storage**: Ensure you're using S3 or similar
4. **Application servers**: Run multiple instances behind a load balancer
5. **Cache**: Use Redis cluster for high availability

---

## Support & Documentation

For detailed API documentation and feature guides, see:
- `README_SOCIAL_INTEGRATIONS.md` - Social media integration details
- `SOCIAL_INTEGRATIONS_BUILD.md` - Implementation specifics
- `ACCOUNT_MANAGER_ENHANCEMENTS.md` - Account manager features
- `CLIENT_CAPTURE_AND_REMINDERS.md` - Client-facing features

## Quick Start Commands

```bash
# Start development server
php artisan serve

# Watch for frontend changes (development)
npm run dev

# Process queued jobs manually
php artisan queue:work

# Run scheduler manually (testing)
php artisan schedule:run

# Clear all caches
php artisan optimize:clear
```

Good luck with your launch!
