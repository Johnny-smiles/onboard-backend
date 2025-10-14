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
            'password' => 'required|string|min:6|confirmed',
            'role' => 'in:admin,client',
            'client_id' => 'nullable|exists:clients,id',
        ]);

        $data['password'] = Hash::make($data['password']);

        $roleName = $data['role'] ?? 'client';
        $data['role'] = $roleName;

        $user = User::create($data);

        Role::findOrCreate($roleName);
        $user->syncRoles([$roleName]);

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('roles:id,name'),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials']);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('roles:id,name'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
