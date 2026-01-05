# On Brand - Documentation Index

All documentation for the On Brand application.

---

## 📚 Quick Start

**Start Here →** [GETTING_STARTED.md](GETTING_STARTED.md)
- Local development setup
- How to connect social media accounts
- Testing the complete flow
- Troubleshooting guide

---

## 📖 Core Documentation

### For Development

**[QUICK_REFERENCE.md](QUICK_REFERENCE.md)**
- Common commands cheat sheet
- Database queries
- API endpoints
- Troubleshooting quick fixes

**[IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)**
- What was built and how it works
- Technical implementation details
- System architecture
- Code flow diagrams

### Feature Documentation

**[README_SOCIAL_INTEGRATIONS.md](README_SOCIAL_INTEGRATIONS.md)**
- Social media integration architecture
- OAuth flow details
- Token management strategy
- Provider-specific requirements
- Best practices

**[SOCIAL_INTEGRATIONS_BUILD.md](SOCIAL_INTEGRATIONS_BUILD.md)**
- Step-by-step build instructions
- Database schema for integrations
- API controller specifications
- Service layer details

**[ACCOUNT_MANAGER_ENHANCEMENTS.md](ACCOUNT_MANAGER_ENHANCEMENTS.md)**
- Photo review workflow
- Admin features
- Client management
- Publishing capabilities

**[CLIENT_CAPTURE_AND_REMINDERS.md](CLIENT_CAPTURE_AND_REMINDERS.md)**
- Shot recipes feature
- Client capture workflow
- Reminder system
- Guided photo capture

### UI/UX Documentation

**[buildplan/ONBRAND_UI_SPEC.md](buildplan/ONBRAND_UI_SPEC.md)**
- Design system specification
- UI components
- Tailwind configuration
- Design tokens

**[buildplan/UPGRADE_PLAN.md](buildplan/UPGRADE_PLAN.md)**
- Package upgrades
- UI enhancements
- Tooling setup

---

## 🚀 Deployment & Launch

**[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)** ⭐ CRITICAL
- Production environment setup
- Server configuration (Nginx/Apache)
- Queue worker setup (Supervisor)
- Scheduler configuration
- SSL certificate setup
- File storage (S3) configuration
- Monitoring and logs
- Backup strategy
- Security checklist
- Performance optimization

**[LAUNCH_CHECKLIST.md](LAUNCH_CHECKLIST.md)** ⭐ CRITICAL
- Pre-launch checklist (70+ items)
- Environment configuration steps
- Social media API setup
- Testing procedures
- Monitoring plan
- Rollback procedures
- Success metrics

---

## 📁 Code Organization

### Backend (Laravel)

```
app/
├── Http/Controllers/Api/
│   ├── OAuthController.php          # OAuth redirect & callback
│   ├── SocialIntegrationController.php
│   └── PublishController.php
├── Services/
│   ├── SocialIntegrationService.php # OAuth & token management
│   └── PublishService.php           # Social media publishing
├── Jobs/
│   ├── ProcessPhotoPublications.php # Publishing queue
│   └── RefreshSocialTokens.php      # Token refresh
├── Models/
│   ├── SocialIntegration.php
│   ├── Photo.php
│   ├── PhotoPublication.php
│   └── Client.php
└── Console/
    └── Kernel.php                    # Scheduled tasks
```

### Frontend (Vue)

```
resources/js/portal/
├── views/
│   ├── AdminReview.vue
│   ├── ClientSocialConnections.vue
│   └── ...
├── components/
│   ├── Uploader.vue
│   ├── PublishDrawer.vue
│   └── ...
└── services/
    └── api.ts
```

---

## 🔑 Key Files to Know

### Configuration
- `.env.example` - Environment variables template
- `config/services.php` - Social media API credentials
- `config/cors.php` - CORS settings

### Routes
- `routes/api.php` - All API endpoints
- `routes/web.php` - Web routes (portal)

### Database
- `database/migrations/` - All database tables
- `database/seeders/DatabaseSeeder.php` - Demo data

### Scheduled Tasks
- `app/Console/Kernel.php` - Cron jobs configuration

---

## 🔍 How to Find Information

### "How do I connect to Facebook/Instagram?"
→ [GETTING_STARTED.md](GETTING_STARTED.md) - Meta section
→ [README_SOCIAL_INTEGRATIONS.md](README_SOCIAL_INTEGRATIONS.md) - Technical details

### "How do I publish a photo?"
→ [GETTING_STARTED.md](GETTING_STARTED.md) - Testing section
→ [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) - Flow diagrams

### "How do I deploy to production?"
→ [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) - Complete guide
→ [LAUNCH_CHECKLIST.md](LAUNCH_CHECKLIST.md) - Verification steps

### "What commands do I need?"
→ [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - All commands

### "How does OAuth work?"
→ [README_SOCIAL_INTEGRATIONS.md](README_SOCIAL_INTEGRATIONS.md) - Architecture
→ [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) - Flow diagram

### "How do I troubleshoot issues?"
→ [GETTING_STARTED.md](GETTING_STARTED.md) - Troubleshooting section
→ [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Quick fixes
→ [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) - Common issues

---

## 📊 Documentation by Role

### Developers (Building Features)
1. [README_SOCIAL_INTEGRATIONS.md](README_SOCIAL_INTEGRATIONS.md)
2. [SOCIAL_INTEGRATIONS_BUILD.md](SOCIAL_INTEGRATIONS_BUILD.md)
3. [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)
4. [QUICK_REFERENCE.md](QUICK_REFERENCE.md)

### DevOps/SysAdmins (Deploying)
1. [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) ⭐
2. [LAUNCH_CHECKLIST.md](LAUNCH_CHECKLIST.md) ⭐
3. [QUICK_REFERENCE.md](QUICK_REFERENCE.md)

### QA/Testers (Testing)
1. [GETTING_STARTED.md](GETTING_STARTED.md)
2. [LAUNCH_CHECKLIST.md](LAUNCH_CHECKLIST.md)
3. [QUICK_REFERENCE.md](QUICK_REFERENCE.md)

### Product Managers (Features)
1. [ACCOUNT_MANAGER_ENHANCEMENTS.md](ACCOUNT_MANAGER_ENHANCEMENTS.md)
2. [CLIENT_CAPTURE_AND_REMINDERS.md](CLIENT_CAPTURE_AND_REMINDERS.md)
3. [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)

### New Team Members (Onboarding)
1. [GETTING_STARTED.md](GETTING_STARTED.md) ⭐
2. [README_SOCIAL_INTEGRATIONS.md](README_SOCIAL_INTEGRATIONS.md)
3. [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)
4. [QUICK_REFERENCE.md](QUICK_REFERENCE.md)

---

## 🎯 Common Workflows

### Setting Up for First Time
```
1. Read GETTING_STARTED.md
2. Get API credentials (Facebook, Google)
3. Follow setup steps
4. Test OAuth flow
5. Test publishing
```

### Deploying to Production
```
1. Read DEPLOYMENT_GUIDE.md thoroughly
2. Work through LAUNCH_CHECKLIST.md
3. Configure production .env
4. Set up server (Nginx, Supervisor)
5. Run deployment commands
6. Monitor with provided queries
```

### Troubleshooting Issues
```
1. Check GETTING_STARTED.md troubleshooting
2. Use QUICK_REFERENCE.md for commands
3. Check logs (tail -f storage/logs/laravel.log)
4. Consult DEPLOYMENT_GUIDE.md common issues
```

### Adding a New Feature
```
1. Review IMPLEMENTATION_SUMMARY.md for architecture
2. Check existing code organization
3. Follow Laravel best practices
4. Test with QUICK_REFERENCE.md commands
5. Update documentation
```

---

## 📝 Documentation Standards

All documentation files use:
- Markdown formatting
- Clear section headers
- Code blocks with syntax highlighting
- Step-by-step instructions
- Examples and screenshots where helpful
- Links to related documentation

---

## 🔄 Keeping Documentation Updated

When you make changes:
1. Update relevant .md files
2. Add to IMPLEMENTATION_SUMMARY.md if it's a new feature
3. Update QUICK_REFERENCE.md if new commands are added
4. Update LAUNCH_CHECKLIST.md if it affects deployment

---

## 💡 Documentation Tips

- **Use Ctrl+F** to search within documents
- **Start with GETTING_STARTED.md** if you're new
- **Keep QUICK_REFERENCE.md** bookmarked for commands
- **Follow LAUNCH_CHECKLIST.md** strictly before production
- **Reference DEPLOYMENT_GUIDE.md** for all server setup

---

## 📞 Need More Help?

If documentation doesn't cover your question:
1. Check Laravel documentation: https://laravel.com/docs
2. Check Socialite docs: https://laravel.com/docs/socialite
3. Check Facebook Graph API: https://developers.facebook.com/docs
4. Check Google My Business API: https://developers.google.com/my-business

---

**Last Updated:** January 2026
**Version:** 1.0
**Status:** Production Ready ✅
