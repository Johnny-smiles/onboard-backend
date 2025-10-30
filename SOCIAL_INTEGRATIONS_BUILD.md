# On Brand — Social Integrations Build Plan

This document tells Codex how to add the **client social connections** feature to the existing Laravel + Vue app.

We already have: `README_SOCIAL_INTEGRATIONS.md` (high-level design, providers, tokens, scopes).

This file = “do these concrete steps in code.”

---

## 0. Context / assumptions

- Repo root: `onbrand-backend/`
- Laravel + Sanctum already set up
- We already have a `clients` table/model
- We already have the Vue “portal” (e.g. `/portal/...`)
- We want **admin users** to open a page, pick a client, and connect Meta / Google / WordPress
- We will plug in **real Socialite redirects later** — for now, stubs/placeholders are fine
- Tokens must be **encrypted** in DB

---

## 1. Add DB migration

**Create file:** `onbrand-backend/database/migrations/2025_10_29_000000_create_social_integrations_table.php`

**Purpose:** store one row per **client + provider** with encrypted tokens.

**Columns:**

- `id`
- `client_id` (FK → clients)
- `provider` (string) — e.g. `meta`, `google`, `wordpress`
- `account_name` (string, nullable)
- `external_ids` (json, nullable) — page id, ig business id, GBP locations
- `scopes` (json, nullable)
- `access_token_encrypted` (text, nullable)
- `refresh_token_encrypted` (text, nullable)
- `expires_at` (timestamp, nullable)
- `connected_at` (timestamp, nullable)
- `status` (string, default `active`)
- timestamps
- FK to `clients` with cascade delete
- index on (`client_id`, `provider`)

---

## 2. Add Eloquent model

**Create file:** `onbrand-backend/app/Models/SocialIntegration.php`

**Rules:**
- `$fillable` = all columns above
- `$casts` = `external_ids` and `scopes` to array, `expires_at` + `connected_at` to datetime
- `client()` relationship → `belongsTo(Client::class)`

---

## 3. Add Service (skeleton)

**Create file:** `onbrand-backend/app/Services/SocialIntegrationService.php`

**Responsibilities:**

1. `upsertForClient(Client $client, array $data): SocialIntegration`
   - required: `provider`
   - optional: `account_name`, `external_ids`, `scopes`, `expires_at`, `status`
   - if `access_token` present → encrypt: `Crypt::encryptString(...)` into `access_token_encrypted`
   - if `refresh_token` present → encrypt into `refresh_token_encrypted`
   - `connected_at = now()`
   - `updateOrCreate` on (`client_id`, `provider`)

2. `getRedirectUrl(string $provider, Client $client): string`
   - **placeholder**: return `/oauth/callback/{provider}?client_id={id}`
   - comment: “TODO: replace with Socialite redirect (see README_SOCIAL_INTEGRATIONS.md)”

3. `refreshIfNeeded(SocialIntegration $integration): void`
   - empty method with TODO
   - comment: “Google: use refresh token; Meta: long-lived tokens; see README_SOCIAL_INTEGRATIONS.md”

---

## 4. Add API controller

**Create file:** `onbrand-backend/app/Http/Controllers/Api/SocialIntegrationController.php`

**Endpoints:**

1. `GET /api/v1/clients/{client}/integrations`
   - auth:sanctum
   - if user is admin → allow
   - else if user has `client_id == {client}` → allow
   - else 403
   - return all `SocialIntegration::where('client_id', $client->id)->get()`

2. `POST /api/v1/clients/{client}/integrations`
   - validate:
     - `provider` (required, string)
     - `account_name` (nullable, string)
     - `external_ids` (nullable, array)
     - `scopes` (nullable, array)
     - `access_token` (nullable, string)
     - `refresh_token` (nullable, string)
     - `expires_at` (nullable, date)
     - `status` (nullable, string)
   - call service `upsertForClient(...)`
   - return 201

3. `DELETE /api/v1/clients/{client}/integrations/{integration}`
   - check integration belongs to client
   - delete
   - return `{"deleted": true}`

4. `GET /api/v1/clients/{client}/integrations/{provider}/redirect`
   - call service `getRedirectUrl(...)`
   - return `{ "url": "..." }`
   - this is what the Vue page will hit

**Authorization helper in controller:**
- if `$user->role === 'admin'` → OK
- else if `$user->client_id === $client->id` → OK
- else 403

---

## 5. Add routes

**Edit:** `onbrand-backend/routes/api.php`

Inside `Route::prefix('v1')->group(...)` and inside the existing `auth:sanctum` group, add:

```php
Route::get('clients/{client}/integrations', [\App\Http\Controllers\Api\SocialIntegrationController::class, 'index']);
Route::post('clients/{client}/integrations', [\App\Http\Controllers\Api\SocialIntegrationController::class, 'store']);
Route::delete('clients/{client}/integrations/{integration}', [\App\Http\Controllers\Api\SocialIntegrationController::class, 'destroy']);
Route::get('clients/{client}/integrations/{provider}/redirect', [\App\Http\Controllers\Api\SocialIntegrationController::class, 'redirect']);
