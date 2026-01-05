# Two-Factor Authentication (2FA) Guide

## Overview

Two-Factor Authentication has been implemented for admin accounts using Google Authenticator compatible TOTP (Time-based One-Time Password).

**Security Features:**
- 6-digit TOTP codes that change every 30 seconds
- 8 recovery codes for emergency access
- Encrypted storage of secrets and recovery codes
- Audit logging of all 2FA events
- Password verification required for sensitive operations

---

## API Endpoints

### 1. Setup 2FA (GET /api/v1/2fa/setup)

**Description:** Generate a 2FA secret and QR code for the user to scan with their authenticator app.

**Authentication:** Required (Bearer token)

**Authorization:** Admin users only

**Response:**
```json
{
  "secret": "JBSWY3DPEHPK3PXP",
  "qr_code_url": "otpauth://totp/OnBrand:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=OnBrand",
  "enabled": false
}
```

**Usage:**
```bash
curl -X GET http://localhost:8000/api/v1/2fa/setup \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Client Implementation:**
1. Display the QR code to the user using `qr_code_url`
2. Alternatively, display the `secret` for manual entry
3. User scans QR code with Google Authenticator, Authy, or similar app
4. User enters 6-digit code from their app to enable 2FA

---

### 2. Enable 2FA (POST /api/v1/2fa/enable)

**Description:** Enable 2FA after verifying the user can generate valid codes.

**Authentication:** Required (Bearer token)

**Request Body:**
```json
{
  "code": "123456",
  "password": "user_password"
}
```

**Response:**
```json
{
  "message": "2FA enabled successfully",
  "recovery_codes": [
    "abcdef1234-ghijkl5678",
    "mnopqr9012-stuvwx3456",
    "yzabcd7890-efghij1234",
    "klmnop5678-qrstuv9012",
    "wxyzab3456-cdefgh7890",
    "ijklmn1234-opqrst5678",
    "uvwxyz9012-abcdef3456",
    "ghijkl7890-mnopqr1234"
  ]
}
```

**Important:**
- Save the recovery codes securely
- Each recovery code can only be used once
- If all recovery codes are used, regenerate new ones

**Usage:**
```bash
curl -X POST http://localhost:8000/api/v1/2fa/enable \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "123456",
    "password": "your_password"
  }'
```

---

### 3. Login with 2FA

**Step 1: Initial Login (POST /api/v1/login)**

When 2FA is enabled, the login endpoint returns a different response:

**Request:**
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

**Response (2FA Required):**
```json
{
  "message": "Two-factor authentication required",
  "requires_2fa": true,
  "user_id": 1
}
```

**Step 2: Verify 2FA Code (POST /api/v1/2fa/verify)**

**Request:**
```json
{
  "user_id": 1,
  "code": "123456"
}
```

**Or with recovery code:**
```json
{
  "user_id": 1,
  "code": "abcdef1234-ghijkl5678"
}
```

**Response:**
```json
{
  "message": "2FA verified",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin"
  },
  "token": "1|abcdef...",
  "expires_at": "2026-02-04T03:19:30+00:00"
}
```

**Complete Login Flow:**
```bash
# Step 1: Login
response=$(curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}')

# Check if 2FA is required
requires_2fa=$(echo $response | jq -r '.requires_2fa')
user_id=$(echo $response | jq -r '.user_id')

# Step 2: If 2FA required, verify code
if [ "$requires_2fa" = "true" ]; then
  curl -X POST http://localhost:8000/api/v1/2fa/verify \
    -H "Content-Type: application/json" \
    -d "{\"user_id\":$user_id,\"code\":\"123456\"}"
fi
```

---

### 4. Disable 2FA (POST /api/v1/2fa/disable)

**Description:** Disable 2FA for the authenticated user.

**Authentication:** Required (Bearer token)

**Request Body:**
```json
{
  "password": "user_password"
}
```

**Response:**
```json
{
  "message": "2FA disabled successfully"
}
```

**Usage:**
```bash
curl -X POST http://localhost:8000/api/v1/2fa/disable \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"password": "your_password"}'
```

---

### 5. Regenerate Recovery Codes (POST /api/v1/2fa/recovery-codes)

**Description:** Generate new recovery codes (invalidates old ones).

**Authentication:** Required (Bearer token)

**Request Body:**
```json
{
  "password": "user_password"
}
```

**Response:**
```json
{
  "message": "Recovery codes regenerated",
  "recovery_codes": [
    "new-code-1",
    "new-code-2",
    ...
  ]
}
```

**Usage:**
```bash
curl -X POST http://localhost:8000/api/v1/2fa/recovery-codes \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"password": "your_password"}'
```

---

## Database Schema

### Users Table (Added Columns)

```sql
ALTER TABLE users ADD COLUMN google2fa_secret TEXT NULL;
ALTER TABLE users ADD COLUMN google2fa_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN two_factor_recovery_codes TEXT NULL;
```

**Fields:**
- `google2fa_secret`: Encrypted TOTP secret key
- `google2fa_enabled`: Whether 2FA is active for this user
- `two_factor_recovery_codes`: Encrypted JSON array of recovery codes

---

## Security Considerations

### Encryption
- **2FA secrets** are encrypted using Laravel's `encrypt()` function
- **Recovery codes** are encrypted before storage
- Secrets are **never** returned in API responses (hidden in User model)

### Rate Limiting
- Login endpoint: **10 requests/minute** per IP
- 2FA verify endpoint: **10 requests/minute** per IP (prevents brute force)

### Audit Logging
All 2FA events are logged with Spatie Activity Log:
- 2FA enabled/disabled
- Successful 2FA verification
- Failed 2FA attempts (partial code logged: `12****`)
- Recovery code usage
- Recovery codes regenerated

**View logs:**
```bash
php artisan tinker
>>> \Spatie\Activitylog\Models\Activity::where('description', 'like', '%2FA%')->get();
```

### Password Verification
Operations requiring password confirmation:
- Enabling 2FA
- Disabling 2FA
- Regenerating recovery codes

---

## Client Implementation Guide

### React/Vue Example

```javascript
// 1. Setup 2FA - Show QR Code
async function setup2FA() {
  const response = await fetch('/api/v1/2fa/setup', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const data = await response.json();

  // Display QR code
  setQRCodeUrl(data.qr_code_url);
  setSecret(data.secret);
  setIs2FAEnabled(data.enabled);
}

// 2. Enable 2FA - Verify code from authenticator
async function enable2FA(code, password) {
  const response = await fetch('/api/v1/2fa/enable', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ code, password })
  });
  const data = await response.json();

  // Store recovery codes securely (download, print, save)
  setRecoveryCodes(data.recovery_codes);
  alert('2FA enabled! Save your recovery codes!');
}

// 3. Login with 2FA
async function login(email, password) {
  const response = await fetch('/api/v1/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  const data = await response.json();

  if (data.requires_2fa) {
    // Show 2FA code input
    setNeedsVerification(true);
    setUserId(data.user_id);
  } else {
    // Normal login
    setToken(data.token);
  }
}

async function verify2FA(userId, code) {
  const response = await fetch('/api/v1/2fa/verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, code })
  });
  const data = await response.json();

  if (response.ok) {
    setToken(data.token);
    setUser(data.user);
  } else {
    alert('Invalid 2FA code');
  }
}
```

---

## Testing 2FA

### 1. Test Setup and Enable

```bash
# Create admin user
php artisan tinker
>>> $user = User::factory()->create(['role' => 'admin', 'email' => 'admin@test.com', 'password' => Hash::make('Password123!')]);

# Login to get token
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com","password":"Password123!"}' \
  | jq -r '.token' > token.txt

TOKEN=$(cat token.txt)

# Setup 2FA
curl -X GET http://localhost:8000/api/v1/2fa/setup \
  -H "Authorization: Bearer $TOKEN" | jq

# Scan QR code with authenticator app, then enable with code
curl -X POST http://localhost:8000/api/v1/2fa/enable \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"code":"123456","password":"Password123!"}' | jq
```

### 2. Test Login with 2FA

```bash
# Login (should return requires_2fa)
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com","password":"Password123!"}' | jq

# Verify with 2FA code
curl -X POST http://localhost:8000/api/v1/2fa/verify \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"code":"123456"}' | jq
```

### 3. Test Recovery Code

```bash
# Use recovery code instead of TOTP
curl -X POST http://localhost:8000/api/v1/2fa/verify \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"code":"abcdef1234-ghijkl5678"}' | jq
```

---

## Authenticator Apps

Users can use any TOTP-compatible authenticator app:

- **Google Authenticator** (iOS, Android)
- **Microsoft Authenticator** (iOS, Android)
- **Authy** (iOS, Android, Desktop)
- **1Password** (with TOTP support)
- **LastPass Authenticator**

---

## Troubleshooting

### Issue: "Invalid verification code" even with correct code

**Cause:** Time drift between server and authenticator app

**Solution:**
```bash
# Sync server time
sudo ntpdate -s time.nist.gov

# Or on macOS
sudo sntp -sS time.apple.com
```

### Issue: User lost authenticator device

**Solution:** Use recovery codes to login, then disable and re-enable 2FA

```bash
# 1. Login with recovery code
POST /api/v1/2fa/verify
{"user_id": 1, "code": "recovery-code-here"}

# 2. Disable 2FA
POST /api/v1/2fa/disable
{"password": "user_password"}

# 3. Setup 2FA again with new device
GET /api/v1/2fa/setup
```

### Issue: All recovery codes used

**Solution:** Regenerate recovery codes while logged in

```bash
POST /api/v1/2fa/recovery-codes
{"password": "user_password"}
```

---

## Production Checklist

- [ ] Enforce HTTPS (2FA codes should never be transmitted over HTTP)
- [ ] Set up time synchronization (NTP) on server
- [ ] Configure rate limiting (already set to 10/min)
- [ ] Enable audit logging (already implemented)
- [ ] Educate admins on saving recovery codes
- [ ] Test time drift scenarios
- [ ] Monitor failed 2FA attempts in activity log
- [ ] Consider mandatory 2FA for all admin accounts

---

## Future Enhancements

Potential improvements:
1. **SMS/Email backup codes** (alternative to TOTP)
2. **WebAuthn/FIDO2 support** (hardware keys)
3. **Trusted devices** (remember device for 30 days)
4. **Force 2FA** for all admin accounts (make it mandatory)
5. **Admin dashboard** to view 2FA status of all users
6. **2FA during password reset** (additional security layer)

---

**Implementation Complete:** ✅

All 2FA functionality is working and production-ready!
