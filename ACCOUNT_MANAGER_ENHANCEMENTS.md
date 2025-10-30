# On Brand — Account Manager Enhancements

Goal: make the app actually useful for account managers (AMs) who need to
1) approve what field staff uploaded,
2) organize by job/service, and
3) send to social channels we already connected.

This builds on:
- `README_SOCIAL_INTEGRATIONS.md`
- `SOCIAL_INTEGRATIONS_BUILD.md`

We will add:
1. extra fields on photos to make uploads meaningful
2. a “Review Queue” view for AMs
3. a “Channels” layer on top of social_integrations
4. a client approval (share) link
5. client-level caption presets
6. integration health

---

## 0) Assumptions

- Laravel app in `onbrand-backend/`
- We already have `photos` (or similar) table that stores client uploads
- We already have `clients` table
- We already have an authenticated portal (`/portal/...`)
- We already have `social_integrations` from the previous build

If any name doesn’t match, create the missing file but do **not** delete existing code.

---

## 1) Add meaningful photo fields

We want uploads to say: what job, where, what kind of shot.

**Migration:**  
Create: `onbrand-backend/database/migrations/2025_10_29_010000_add_metadata_to_photos_table.php`

Add columns to `photos`:

- `job_name` (string, nullable)
- `location` (string, nullable)
- `shot_type` (string, nullable) — e.g. `before`, `after`, `wide`, `team`, `detail`
- `notes` (text, nullable)
- index on (`client_id`, `job_name`) if `client_id` exists

**Model:**  
Update `onbrand-backend/app/Models/Photo.php` to add these to `$fillable`.

**Why:** this powers filtering in the review queue.

If `photos` doesn’t yet have review fields, add these **in the same migration**:

- `review_status` (string, default `pending`)
- `review_notes` (text, nullable)
- `reviewed_by` (unsignedBigInteger, nullable)
- `reviewed_at` (timestamp, nullable)

---

## 2) Review Queue (for AMs)

We want one screen: “show me all unreviewed photos, newest first, filter by client.”

**Create controller:**  
`onbrand-backend/app/Http/Controllers/Api/PhotoReviewController.php`

**Endpoints:**

1. `GET /api/v1/review/photos`
   - `auth:sanctum`
   - if user is **admin** → can see all
   - else → only photos where `client_id = user->client_id`
   - return newest first
   - support query params:
     - `client_id` (optional)
     - `status` (`pending|approved|rejected`, optional)
     - `shot_type` (optional)

2. `POST /api/v1/review/photos/{photo}/approve`
   - set:
     - `review_status = 'approved'`
     - `reviewed_by = auth()->id()`
     - `reviewed_at = now()`

3. `POST /api/v1/review/photos/{photo}/reject`
   - set:
     - `review_status = 'rejected'`
     - `review_notes = $request->input('reason')`
     - `reviewed_by = auth()->id()`
     - `reviewed_at = now()`

**Routes:** in `onbrand-backend/routes/api.php` inside `Route::middleware('auth:sanctum')`:
```php
Route::get('review/photos', [\App\Http\Controllers\Api\PhotoReviewController::class, 'index']);
Route::post('review/photos/{photo}/approve', [\App\Http\Controllers\Api\PhotoReviewController::class, 'approve']);
Route::post('review/photos/{photo}/reject', [\App\Http\Controllers\Api\PhotoReviewController::class, 'reject']);
