# On Brand — Client Capture & Reminders

Goal: help CLIENTS (not just account managers) actually take the right photos, at the right time, with guidance — and get those photos into the same pipeline the AMs are already using.

This builds on:
- `README_SOCIAL_INTEGRATIONS.md`
- `SOCIAL_INTEGRATIONS_BUILD.md`
- `ACCOUNT_MANAGER_ENHANCEMENTS.md`

We will add:
1. client-facing “Capture / Upload” flow with coaching
2. shot recipes per client (or per industry)
3. reminder schedules (email/SMS stubs)
4. “what we still need” panel
5. admin view to define shot recipes + reminders

---

## 0) Assumptions

- Laravel app in `onbrand-backend/`
- We have authenticated users that belong to a `client_id`
- We already have a `photos` table (with the extra metadata from the AM build)
- We already have a portal (Vue) at `/portal/...`
- Notifications (email/SMS) can be **stubbed** for now — just queue jobs/log
- We can later wire Twilio / Mailgun / Postmark

---

## 1) Shot recipes

We need a way for an admin to define “what we want the client to shoot.”

**Create table:**
`onbrand-backend/database/migrations/2025_10_29_050000_create_shot_recipes_table.php`

Columns:
- `id`
- `client_id` (nullable) — if null → GLOBAL / default recipe
- `name` (string) — e.g. “Before & After – Electrical”
- `description` (text, nullable)
- `steps` (json) — ordered list like:
  ```json
  [
    { "label": "Before — wide", "shot_type": "before", "notes": "Step back to show space" },
    { "label": "After — wide", "shot_type": "after", "notes": "Match angle from before" },
    { "label": "Detail", "shot_type": "detail" }
  ]
