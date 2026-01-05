# On Brand - Launch Checklist

## Pre-Launch Checklist

### 1. Environment Setup
- [ ] Copy `.env.example` to `.env`
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Generate `APP_KEY` with `php artisan key:generate`
- [ ] Configure database credentials
- [ ] Set up S3 or file storage
- [ ] Configure mail provider (Postmark/Mailgun/SES)

### 2. Social Media API Configuration

#### Meta (Facebook/Instagram)
- [ ] Create Facebook App at https://developers.facebook.com
- [ ] Add `META_CLIENT_ID` to `.env`
- [ ] Add `META_CLIENT_SECRET` to `.env`
- [ ] Configure OAuth redirect URL: `{YOUR_URL}/api/oauth/callback/meta`
- [ ] Request necessary permissions (pages_manage_posts, instagram_content_publish)

#### Google Business Profile
- [ ] Create project at https://console.cloud.google.com
- [ ] Enable Google My Business API
- [ ] Create OAuth 2.0 credentials
- [ ] Add `GOOGLE_CLIENT_ID` to `.env`
- [ ] Add `GOOGLE_CLIENT_SECRET` to `.env`
- [ ] Configure OAuth redirect URL: `{YOUR_URL}/api/oauth/callback/google`

### 3. Database & Migrations
- [ ] Create production database
- [ ] Run `php artisan migrate --force`
- [ ] Optionally seed demo data: `php artisan db:seed`
- [ ] Verify all tables created successfully

### 4. Dependencies
- [ ] Run `composer install --no-dev --optimize-autoloader`
- [ ] Run `npm ci`
- [ ] Run `npm run build`
- [ ] Verify all packages installed correctly

### 5. Laravel Optimizations
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Run `php artisan storage:link` (if using local storage)

### 6. Queue Workers
- [ ] Install and configure Supervisor
- [ ] Create supervisor config for queue workers
- [ ] Start queue workers: `sudo supervisorctl start onboard-worker:*`
- [ ] Verify workers are running: `sudo supervisorctl status`

### 7. Scheduler
- [ ] Add cron entry: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Test scheduler: `php artisan schedule:run`
- [ ] Verify scheduled jobs appear in logs

### 8. Web Server
- [ ] Configure Nginx or Apache
- [ ] Point document root to `/public`
- [ ] Configure PHP-FPM
- [ ] Test web server is serving the app

### 9. SSL Certificate
- [ ] Install Let's Encrypt with Certbot
- [ ] Verify SSL certificate is working
- [ ] Configure auto-renewal

### 10. Security
- [ ] Ensure `APP_DEBUG=false` in production
- [ ] Review CORS settings in `config/cors.php`
- [ ] Set appropriate file upload limits
- [ ] Configure rate limiting on API routes
- [ ] Review firewall rules
- [ ] Secure `.env` file permissions (chmod 600)

### 11. Backups
- [ ] Set up automated database backups
- [ ] Configure S3 bucket versioning (if using S3)
- [ ] Test backup restoration process
- [ ] Document backup/restore procedures

### 12. Monitoring & Logging
- [ ] Set up log rotation for Laravel logs
- [ ] Configure error monitoring (Sentry/Bugsnag)
- [ ] Set up uptime monitoring
- [ ] Configure disk space alerts
- [ ] Set up queue monitoring

## Testing Before Launch

### Functional Testing

#### User Authentication
- [ ] Register new user
- [ ] Login as client user
- [ ] Login as admin user
- [ ] Password reset flow

#### Photo Management
- [ ] Client can upload photos
- [ ] Photos appear in admin review queue
- [ ] Admin can approve photos
- [ ] Admin can reject photos with notes
- [ ] Bulk operations work (approve, delete, export)

#### Social Media Integration - Meta
- [ ] Navigate to Admin → Clients → Social Connections
- [ ] Click "Connect Meta"
- [ ] Complete OAuth flow
- [ ] Verify integration saved with page_id
- [ ] Check encryption of access_token in database
- [ ] Verify expiration date is set correctly

#### Social Media Integration - Google
- [ ] Click "Connect Google"
- [ ] Complete OAuth flow
- [ ] Verify locations are fetched
- [ ] Check refresh_token is stored encrypted
- [ ] Verify integration status is 'active'

#### Photo Publishing - Meta
- [ ] Upload and approve a photo
- [ ] Click "Publish" → Select Meta
- [ ] Verify publication queued in `photo_publications` table
- [ ] Run `php artisan queue:work` manually
- [ ] Verify photo published to Facebook Page
- [ ] Check `published_at` timestamp updated
- [ ] Verify `external_id` saved

#### Photo Publishing - Google
- [ ] Publish approved photo to Google Business Profile
- [ ] Verify post appears on Google My Business
- [ ] Check publication status updated

#### Token Refresh
- [ ] Manually dispatch: `\App\Jobs\RefreshSocialTokens::dispatch()`
- [ ] Check logs for refresh activity
- [ ] Verify token expiration extended
- [ ] Test with expired token (manually set expires_at in past)

#### Shot Recipes (Client Guidance)
- [ ] Admin creates shot recipe
- [ ] Client views recipe in capture flow
- [ ] Client uploads photos following recipe
- [ ] Admin verifies photos match recipe

#### Capture Reminders
- [ ] Admin creates capture reminder for client
- [ ] Verify reminder scheduled correctly
- [ ] Test reminder dispatch (check logs)
- [ ] Verify client receives notification

### Performance Testing
- [ ] Test with 100+ photos uploaded
- [ ] Test concurrent photo uploads
- [ ] Monitor queue processing speed
- [ ] Check database query performance
- [ ] Test file upload/download speeds

### Error Handling
- [ ] Test with invalid OAuth credentials
- [ ] Test photo publish with revoked token
- [ ] Test photo publish with deleted integration
- [ ] Verify retry logic on failed publishes
- [ ] Check error logs are comprehensive

## Post-Launch Monitoring

### Week 1
- [ ] Monitor error logs daily
- [ ] Check queue worker status
- [ ] Review photo publication success rate
- [ ] Monitor OAuth token refresh success
- [ ] Check disk space usage
- [ ] Review API rate limits

### Week 2-4
- [ ] Review user feedback
- [ ] Analyze publishing performance
- [ ] Check for any failed jobs
- [ ] Review token expiration patterns
- [ ] Monitor server resource usage

## Rollback Plan

In case of critical issues:

1. **Database**: Keep backup before migrations
   ```bash
   mysqldump -u user -p database > backup-pre-launch.sql
   ```

2. **Code**: Tag current stable version
   ```bash
   git tag -a v1.0-stable -m "Stable pre-launch version"
   git push origin v1.0-stable
   ```

3. **Rollback procedure**:
   ```bash
   git checkout v1.0-stable
   composer install
   php artisan migrate:rollback
   mysql -u user -p database < backup-pre-launch.sql
   php artisan optimize:clear
   sudo supervisorctl restart onboard-worker:*
   ```

## Support Contacts

- **Development Team**: [Your contact]
- **Server Admin**: [Contact]
- **Database Admin**: [Contact]
- **Meta Developer Support**: https://developers.facebook.com/support
- **Google Cloud Support**: https://cloud.google.com/support

## Success Metrics

Track these KPIs after launch:

- [ ] Number of active clients
- [ ] Photos uploaded per day
- [ ] Photos published per day
- [ ] Publishing success rate (target: >95%)
- [ ] OAuth connection success rate
- [ ] Token refresh success rate
- [ ] Average photo approval time
- [ ] Queue processing time
- [ ] API error rate (target: <1%)

---

**Launch Date**: _____________

**Launched By**: _____________

**Notes**:
