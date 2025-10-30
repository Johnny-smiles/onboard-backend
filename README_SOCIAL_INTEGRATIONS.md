
# README_SOCIAL_INTEGRATIONS.md
**Scope:** Best-practice guide for connecting client accounts (Meta/Facebook + Instagram, Google Business Profile, WordPress) and safely publishing content via your Laravel backend. Uses **Laravel Socialite for OAuth** where applicable and provider APIs/SDKs for publishing. Designed for the On Brand stack.

---

## Goals
- Admins can **connect** a client’s social channels (per client).
- App can **store & refresh tokens** securely.
- Jobs can **publish** queued content reliably, with audit logs and retries.
- Clear **separation** between **login to app** vs **connect a channel to publish**.

---

## Architecture Overview

```
Client (Admin) → Onboarding UI → Socialite Redirect
                                ← Socialite Callback (tokens)
                                 ↳ IntegrationService.persist()
                                 ↳ Schedulers: refresh tokens, long-lived rotation
                                 ↳ PublishService: queues → dispatches → audits
```

- **Socialite**: start OAuth, handle callback, obtain tokens.
- **IntegrationService**: normalize provider data, store tokens (encrypted), schedule refresh.
- **PublishService**: accept photo(s)/caption, call provider API, update `photo_publications`.
- **Scheduler/Queues**: cron for token refresh + background publish & retries.

---

## Packages

### PHP (Composer)
```bash
composer require laravel/socialite guzzlehttp/guzzle
# Optional helpers:
composer require facebook/graph-sdk google/apiclient:^2.15
# If using Socialite community providers:
composer require socialiteproviders/manager socialiteproviders/facebook socialiteproviders/google
```
> You can also skip SDKs and call REST endpoints via Guzzle. SDKs sometimes lag features; Graph/Google raw HTTP is fine.

### Frontend
- Use the Admin **Client Onboarding** page with “Connect Meta / Connect Google / Connect WordPress” buttons that hit your backend “redirect” endpoints.

---

## Data Model

### `social_integrations` (per client, per provider)
- `id`
- `client_id` (FK → clients)
- `provider` enum: `meta`, `google`, `wordpress`
- `account_name` (page/site/account label)
- `external_ids` (json; e.g., `{ "page_id":"...", "ig_business_id":"...", "locations":["...","..."] }`)
- `scopes` (json; array of granted scopes)
- `access_token_encrypted` (text, encrypted with Laravel Crypt)
- `refresh_token_encrypted` (text, nullable; encrypted)
- `expires_at` (nullable datetime)
- `connected_at` (datetime)
- `status` enum: `active`, `revoked`, `error`
- Timestamps

**Security:**
- Use `Crypt::encryptString()` / Eloquent cast to encrypt tokens at rest.
- Never store tokens in `.env` or logs.
- Limit which fields are serializable to API responses.

### Publishing audit (already in your app)
- `photo_publications` with `service`, `status`, `payload`, `scheduled_at`, `published_at`, `error`.

---

## Environment Variables

### Meta (Facebook/Instagram)
```
META_CLIENT_ID=...
META_CLIENT_SECRET=...
META_REDIRECT_URI=${APP_URL}/oauth/callback/meta
```
**Scopes (typical):**
- Facebook Page: `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`
- Instagram Business: `instagram_basic`, `instagram_content_publish`
- Also: `public_profile`, `email`

**Token model (important):**
1. Socialite returns a **short‑lived user token**.
2. Exchange for a **long‑lived user token** (Graph endpoint).
3. Fetch **pages** and **Instagram Business accounts** the user manages.
4. For the chosen page/IG account, fetch/store **page token** or **IG token** (some are derived per page).

Keep the **long‑lived user token** plus page/IG tokens in your store and refresh on schedule.

### Google Business Profile (GBP)
```
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=${APP_URL}/oauth/callback/google
```
**Scopes:** `https://www.googleapis.com/auth/business.manage`  
**Important:** Include `access_type=offline&prompt=consent` on the first authorization to receive a **refresh token**. Use Google PHP client or direct REST to refresh access tokens.

### WordPress
Two cases:
- **Self‑hosted WordPress (recommended simple path):** use **Application Passwords** (user creates one in their profile) → store `username` + **app password** (encrypt), then call WP REST with Basic Auth over HTTPS.
- **WordPress.com or OAuth‑enabled WP:** treat like Google/Meta (OAuth 2), store refresh token/expiry, call REST.

Env example for self‑hosted:
```
WP_BASE_URL=https://example.com
# No client id/secret needed for App Passwords; store per-client credentials in DB (encrypted)
```

---

## Routes & Controllers (suggested)

### OAuth/Connect endpoints
- `GET /api/v1/integrations/{provider}/redirect?client_id=...`
  - Validates admin permissions, creates a **state** token (client_id + csrf), stores temp in cache, and sends `Socialite::driver(...)->scopes([...])->with([...])->redirect()`
- `GET /oauth/callback/{provider}`
  - Validates **state**, exchanges code → tokens, performs provider‑specific enrichment (page list, IG business id, GBP locations), persists to `social_integrations`.

### Publishing endpoints
- `POST /api/v1/publish/{provider}` → body: `{ photo_ids: [], client_id, scheduled_at? }`
  - Stores rows in `photo_publications` with status `queued` and scheduled time.

### Admin UI endpoints
- `GET /api/v1/integrations?client_id=...`
- `DELETE /api/v1/integrations/{id}` (revoke locally)
- `POST /api/v1/integrations/{id}/refresh` (manual refresh/test)

---

## IntegrationService (suggested responsibilities)
- `redirect(provider, clientId)` → returns Socialite redirect URL with proper scopes + params.
- `handleCallback(provider, request)`:
  - Verifies state, fetches tokens.
  - For Meta: exchange to long‑lived, fetch pages/IG business accounts, store selection or return list to UI for selection.
  - For Google: store refresh token, fetch **accounts/locations** via GBP API; allow user to select 1+ location IDs to attach.
  - For WordPress: validate site URL and either store Application Password (self‑hosted) or OAuth tokens (WP.com).
- `persist(clientId, provider, tokens, externalIds, accountName, scopes, expiresAt)`
- `refreshIfNeeded(integration)` (used by scheduler)

---

## PublishService (suggested responsibilities)
- `queue(photoIds[], provider, clientId, when?)` → creates `photo_publications` records.
- `dispatchDue()` → finds due items, loads `social_integrations`, and routes to provider‑specific publisher:
  - Meta → Graph API:
    - Facebook Page: `POST /{page-id}/photos` with `source`, `caption`
    - Instagram: (Content Publishing) requires IG Business account; upload container → create media → publish (3‑step process)
  - Google Business Profile:
    - `POST /v1/{location}/localPosts` with media reference (requires media upload step via Google My Business API)
  - WordPress:
    - REST `POST /wp-json/wp/v2/media` (upload), then `POST /wp-json/wp/v2/posts` (attach media, status=publish or future)
- On success: update record to `published` + `published_at` + `external_id` in payload.
- On failure: store error payload, increment retry count, exponential backoff (e.g., 1m, 5m, 15m, 1h; cap 5 tries).

---

## Schedulers & Jobs

### Schedule
Add to `app/Console/Kernel.php`:
- `IntegrationService::refreshIfNeeded()` every **hour** (Google refresh; Meta long‑lived rotation).
- `PublishService::dispatchDue()` every **5 minutes**.

### Queues
- Use a `publish` queue for outbound API calls. Mark jobs retryable with reasonable delays.
- Circuit‑break per provider to avoid hammering APIs if credentials are invalid.

---

## Security & Compliance
- **Encrypt** all tokens and passwords at rest (Laravel Crypt). Never log tokens.
- **Limit scopes** to only what’s needed (principle of least privilege).
- **Rotate** long‑lived tokens periodically; handle revocation gracefully.
- **Per‑client isolation**: never reuse one client’s tokens for another.
- **Data ownership**: clarify in your Terms/Privacy that clients own their media and can revoke at any time.
- **Webhooks** (optional):
  - Meta: subscribe for permission changes/revocations.
  - Google: limited webhook options; rely on refresh / error handling.
  - WordPress: not typical; handle publish errors on response.

---

## Minimal Onboarding UI Spec
- Location: **Admin → Client → “Social Connections”**
- Per provider card:
  - Status (Connected / Not connected)
  - Connected as / Account name
  - “Connect” / “Reconnect” / “Disconnect” buttons
  - Scopes list (read-only)
- Meta: after callback, show **Page list** and **IG Business accounts** to select which to bind.
- Google: list **GBP Locations** (checkboxes) to bind.
- WordPress: fields for **Site URL**; if self‑hosted, **Username + App Password** input (send to backend via HTTPS; backend encrypts).

---

## Environment Setup (Laravel Socialite)

### `config/services.php` example
```php
'facebook' => [
    'client_id' => env('META_CLIENT_ID'),
    'client_secret' => env('META_CLIENT_SECRET'),
    'redirect' => env('META_REDIRECT_URI'),
],

'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],
```

### Redirects
- `META_REDIRECT_URI` → `${APP_URL}/oauth/callback/meta`
- `GOOGLE_REDIRECT_URI` → `${APP_URL}/oauth/callback/google`

When redirecting with Google:
```php
Socialite::driver('google')
  ->scopes(['https://www.googleapis.com/auth/business.manage'])
  ->with(['access_type' => 'offline', 'prompt' => 'consent'])
  ->redirect();
```

Meta exchange (HTTP/Guzzle) after Socialite callback:
- Exchange short‑lived → long‑lived user token:
  - `GET https://graph.facebook.com/oauth/access_token?grant_type=fb_exchange_token&client_id=...&client_secret=...&fb_exchange_token=SHORT_TOKEN`
- Fetch pages + page tokens:
  - `GET https://graph.facebook.com/me/accounts?access_token=LONG_TOKEN`
- Fetch IG business accounts attached to pages:
  - `GET https://graph.facebook.com/{page-id}?fields=instagram_business_account&access_token=PAGE_TOKEN`

Store: long‑lived user token, selected page token or ig business id + token, expiry estimate (Meta returns `expires_in` for some flows).

---

## Error Handling & Retries
- Normalize provider errors into a unified structure in `photo_publications.error` (code/message).
- Backoff with jitter on 429/5xx responses.
- Mark integration `status='error'` if refresh fails repeatedly; surface in the UI.

---

## Local Testing Checklist
1. Create a `Client`, attach an `admin` user.
2. Connect Meta → verify page list populates; pick one and persist.
3. Connect Google → verify locations list; pick one and persist.
4. WordPress (self‑hosted) → create Application Password and save credentials.
5. Upload photo(s) → approve → queue publish to each provider → run scheduler or job worker.
6. Verify `photo_publications` become `published` and store `external_id`.
7. Kill credentials → verify graceful failures and surfaced errors.

---

## Future Enhancements
- Web-based **media editor** before publishing (crop/watermark/text overlays).
- **UTM tagging** on outbounds.
- Per‑provider **caption length** and hashtag helpers.
- **Content calendar** view & drag-to-reschedule.
- **Webhook receivers** to capture post URLs automatically (where supported).
