<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Handle user login and issue API token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Pastikan selalu ada default users untuk testing
        $this->ensureDefaultUsers();

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                ]
            ]
        ]);
    }

    /**
     * Handle user logout (revoke current access token).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    /**
     * Ensure default admin and general manager users exist in the database.
     *
     * @return void
     */
    private function ensureDefaultUsers(): void
    {
        // Create admin user if not exists
        $adminExists = User::where('email', 'admin@ternakpark.com')->exists();
        if (!$adminExists) {
            User::create([
                'name'     => 'Administrator',
                'email'    => 'admin@ternakpark.com',
                'password' => Hash::make('admin123'),
                'role'     => 'administrator',
            ]);
            Log::info('Default admin user created: admin@ternakpark.com / admin123 (role: administrator)');
        }

        // Create general manager user if not exists
        $gmExists = User::where('email', 'manager@ternakpark.com')->exists();
        if (!$gmExists) {
            User::create([
                'name'     => 'General Manager',
                'email'    => 'manager@ternakpark.com',
                'password' => Hash::make('manager123'),
                'role'     => 'general_manager',
            ]);
            Log::info('Default general manager created: manager@ternakpark.com / manager123 (role: general_manager)');
        }
    }
}
