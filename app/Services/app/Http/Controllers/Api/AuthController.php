// app/Http/Controllers/Api/AuthController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Device;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Register new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|unique:users|regex:/^03[0-9]{9}$/',
            'password' => 'required|string|min:8|confirmed',
            'pin' => 'required|string|size:4',
            'device_id' => 'required|string',
            'device_name' => 'required|string',
            'device_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'cnic' => '00000-0000000-0', // Will be updated during KYC
                'password' => Hash::make($request->password),
                'pin' => Hash::make($request->pin),
                'phone_verified_at' => now(),
            ]);

            // Create wallet
            $this->walletService->createWallet($user);

            // Register device
            Device::create([
                'user_id' => $user->id,
                'device_id' => $request->device_id,
                'device_name' => $request->device_name,
                'device_type' => $request->device_type,
                'os_version' => $request->os_version ?? 'Unknown',
                'app_version' => $request->app_version ?? '1.0.0',
                'last_active_at' => now(),
            ]);

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('User registered', ['user_id' => $user->id, 'phone' => $user->phone]);

            return response()->json([
                'status' => 'success',
                'message' => 'Registration successful',
                'data' => [
                    'user' => $user,
                    'wallet' => $user->wallet,
                    'token' => $token,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Registration failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'required|string',
            'two_factor_code' => 'nullable|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check login attempts
        $attemptKey = 'login_attempts:' . $request->phone;
        $attempts = Cache::get($attemptKey, 0);

        if ($attempts >= 5) {
            return response()->json([
                'status' => 'error',
                'message' => 'Too many login attempts. Please try again later.'
            ], 429);
        }

        // Find user
        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Cache::increment($attemptKey);
            Cache::expire($attemptKey, 900); // 15 minutes
            
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if user is active
        if (!$user->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is blocked or suspended'
            ], 403);
        }

        // Check device limit
        if ($user->devices()->where('is_active', true)->count() >= 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'Maximum devices limit reached'
            ], 403);
        }

        // Check 2FA
        if ($user->hasTwoFactorEnabled()) {
            if (empty($request->two_factor_code)) {
                return response()->json([
                    'status' => 'info',
                    'message' => 'Two-factor authentication required',
                    'requires_2fa' => true
                ]);
            }

            // Verify 2FA code
            $secret = $user->two_factor_secret;
            if (!$this->verifyTwoFactorCode($request->two_factor_code, $secret)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid 2FA code'
                ], 401);
            }
        }

        // Update device
        $device = Device::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $request->device_id,
            ],
            [
                'device_name' => $request->device_name ?? 'Unknown Device',
                'device_type' => $request->device_type ?? 'Unknown',
                'os_version' => $request->os_version ?? 'Unknown',
                'app_version' => $request->app_version ?? '1.0.0',
                'last_active_at' => now(),
                'is_active' => true,
            ]
        );

        // Update user login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Clear login attempts
        Cache::forget($attemptKey);

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info('User logged in', ['user_id' => $user->id, 'phone' => $user->phone]);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'wallet' => $user->wallet,
                'device' => $device,
                'token' => $token,
                'kyc_status' => $user->kyc_status,
            ]
        ]);
    }

    /**
     * Verify 2FA code
     */
    protected function verifyTwoFactorCode($code, $secret): bool
    {
        // Mock 2FA verification
        // In production, use a proper 2FA library like PHPGangsta/GoogleAuthenticator
        return $code === '123456' || $code === date('Hi'); // For testing
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Deactivate device
        if ($request->device_id) {
            Device::where('user_id', $user->id)
                  ->where('device_id', $request->device_id)
                  ->update(['is_active' => false]);
        }

        $user->currentAccessToken()->delete();

        Log::info('User logged out', ['user_id' => $user->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }
}
