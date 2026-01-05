<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate 2FA secret and QR code for setup
     */
    public function setup(Request $request)
    {
        $user = $request->user();

        // Only admins should use 2FA
        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Two-factor authentication is only available for admin accounts.',
            ], 403);
        }

        // Generate new secret if not already set
        if (!$user->google2fa_secret) {
            $secret = $this->google2fa->generateSecretKey();
            $user->update(['google2fa_secret' => encrypt($secret)]);
        } else {
            $secret = decrypt($user->google2fa_secret);
        }

        // Generate QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'enabled' => $user->google2fa_enabled,
        ]);
    }

    /**
     * Enable 2FA after verifying the code
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'password' => 'required|string',
        ]);

        $user = $request->user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid password'], 401);
        }

        if (!$user->google2fa_secret) {
            return response()->json(['message' => 'Please setup 2FA first'], 400);
        }

        $secret = decrypt($user->google2fa_secret);

        // Verify the code
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Invalid verification code'], 400);
        }

        // Enable 2FA and generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'google2fa_enabled' => true,
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        // Log 2FA enabled
        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('Two-factor authentication enabled');

        return response()->json([
            'message' => '2FA enabled successfully',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid password'], 401);
        }

        $user->update([
            'google2fa_enabled' => false,
            'google2fa_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        // Log 2FA disabled
        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('Two-factor authentication disabled');

        return response()->json(['message' => '2FA disabled successfully']);
    }

    /**
     * Verify 2FA code during login
     */
    public function verify(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required|string',
        ]);

        $user = \App\Models\User::findOrFail($request->user_id);

        if (!$user->google2fa_enabled) {
            return response()->json(['message' => '2FA is not enabled'], 400);
        }

        $secret = decrypt($user->google2fa_secret);

        // Verify the code (6 digits)
        $valid = strlen($request->code) === 6 && $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            // Check if it's a recovery code (longer format)
            if ($this->verifyRecoveryCode($user, $request->code)) {
                // Issue token after recovery code verification
                $token = $user->createToken('api_token', ['*'], now()->addDays(30))->plainTextToken;

                // Log successful 2FA with recovery code
                activity()
                    ->causedBy($user)
                    ->withProperties(['ip' => $request->ip(), 'method' => 'recovery_code'])
                    ->log('Two-factor authentication verified with recovery code');

                return response()->json([
                    'message' => '2FA verified with recovery code',
                    'user' => $user->load('roles:id,name'),
                    'token' => $token,
                    'expires_at' => now()->addDays(30)->toIso8601String(),
                ]);
            }

            // Log failed 2FA attempt
            activity()
                ->causedBy($user)
                ->withProperties([
                    'ip' => $request->ip(),
                    'code' => substr($request->code, 0, 2).'****',
                ])
                ->log('Failed 2FA verification attempt');

            return response()->json(['message' => 'Invalid verification code'], 400);
        }

        // Issue token after successful 2FA verification
        $token = $user->createToken('api_token', ['*'], now()->addDays(30))->plainTextToken;

        // Log successful 2FA
        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('Two-factor authentication verified');

        return response()->json([
            'message' => '2FA verified',
            'user' => $user->load('roles:id,name'),
            'token' => $token,
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ]);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid password'], 401);
        }

        if (!$user->google2fa_enabled) {
            return response()->json(['message' => '2FA is not enabled'], 400);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        // Log recovery codes regenerated
        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('2FA recovery codes regenerated');

        return response()->json([
            'message' => 'Recovery codes regenerated',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Generate recovery codes
     */
    protected function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = Str::random(10).'-'.Str::random(10);
        }

        return $codes;
    }

    /**
     * Verify a recovery code and invalidate it
     */
    protected function verifyRecoveryCode($user, string $code): bool
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        if (!in_array($code, $recoveryCodes)) {
            return false;
        }

        // Remove used recovery code
        $recoveryCodes = array_values(array_diff($recoveryCodes, [$code]));

        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        // Log recovery code used
        activity()
            ->causedBy($user)
            ->log('2FA recovery code used');

        return true;
    }
}
