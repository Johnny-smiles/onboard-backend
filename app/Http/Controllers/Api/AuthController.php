<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/[a-z]/',      // at least one lowercase letter
                'regex:/[A-Z]/',      // at least one uppercase letter
                'regex:/[0-9]/',      // at least one number
                'regex:/[@$!%*#?&]/', // at least one special character
            ],
            'role' => 'in:admin,client',
            'client_id' => 'nullable|exists:clients,id',
        ]);

        $data['password'] = Hash::make($data['password']);

        $roleName = $data['role'] ?? 'client';
        $data['role'] = $roleName;

        $user = User::create($data);

        Role::findOrCreate($roleName);
        $user->syncRoles([$roleName]);

        // Token expires in 30 days
        $token = $user->createToken('api_token', ['*'], now()->addDays(30))->plainTextToken;

        // Log account creation
        activity()
            ->causedBy($user)
            ->withProperties([
                'email' => $user->email,
                'role' => $user->role,
            ])
            ->log('User account created');

        return response()->json([
            'user' => $user->load('roles:id,name'),
            'token' => $token,
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Log failed attempt
            activity()
                ->withProperties([
                    'email' => $request->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('Failed login attempt');

            throw ValidationException::withMessages(['email' => 'Invalid credentials']);
        }

        // Check if 2FA is enabled for this user
        if ($user->google2fa_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication required',
                'requires_2fa' => true,
                'user_id' => $user->id,
            ], 200);
        }

        // Token expires in 30 days
        $token = $user->createToken('api_token', ['*'], now()->addDays(30))->plainTextToken;

        // Log successful login
        activity()
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log('User logged in');

        return response()->json([
            'user' => $user->load('roles:id,name'),
            'token' => $token,
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
