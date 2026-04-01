<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Find user by login (phone, national_id, or email).
     */
    private function findUserByLogin(string $login): ?User
    {
        return User::where('phone', $login)
            ->orWhere('national_id', $login)
            ->orWhere('email', $login)
            ->first();
    }

    /**
     * POST /api/portallogistice/login
     * Body: login (phone OR national_id OR email), password
     * Do NOT send "email" — use "login" only.
     */
    public function login(Request $request): JsonResponse
    {
        if ($request->has('email') && !$request->filled('login')) {
            return response()->json([
                'success' => false,
                'message' => 'User login uses "login" field (phone, national_id, or email), not "email".',
                'errors' => [
                    'login' => ['Use the "login" field for user login.'],
                    'email' => ['Do not send "email" for this endpoint. Use "login" instead.'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ], [
            'login.required' => 'The login field (phone, national_id, or email) is required.',
            'password.required' => 'The password field is required.',
        ]);

        $user = $this->findUserByLogin($validated['login']);
        if (!$user || !$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة',
            ], 401);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة',
            ], 401);
        }

        // Only allow user role on this endpoint (not admin)
        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'استخدم تسجيل دخول المدير',
            ], 401);
        }

        if ($user->is_first_login) {
            return response()->json([
                'success' => true,
                'requiresOTP' => true,
                'data' => [
                    'user' => $user->toApiArray(),
                ],
            ]);
        }

        $token = $user->api_token ?? Str::random(60);
        $user->forceFill(['api_token' => $token])->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->toApiArray(),
            ],
        ]);
    }

    /**
     * POST /api/portallogistice/admin/login
     * Body: email, password (email only — do NOT send "login").
     */
    public function adminLogin(Request $request): JsonResponse
    {
        if ($request->has('login') && !$request->filled('email')) {
            return response()->json([
                'success' => false,
                'message' => 'Admin login uses "email" field only, not "login".',
                'errors' => [
                    'email' => ['Use the "email" field for admin login.'],
                    'login' => ['Do not send "login" for this endpoint. Use "email" instead.'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'The email field is required for admin login.',
            'email.email' => 'The email must be a valid email address.',
            'password.required' => 'The password field is required.',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user || !$user->isActive() || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة',
            ], 401);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة',
            ], 401);
        }

        if ($user->is_first_login) {
            return response()->json([
                'success' => true,
                'requiresOTP' => true,
                'data' => [
                    'admin' => $user->toApiArray(),
                ],
            ]);
        }

        $token = $user->api_token ?? Str::random(60);
        $user->forceFill(['api_token' => $token])->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'admin' => $user->toApiArray(),
            ],
        ]);
    }

    /**
     * POST /api/portallogistice/send-otp
     * Body: phone (or national_id for Header flow - support both)
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required_without:national_id|string|nullable',
            'national_id' => 'required_without:phone|string|nullable',
        ]);

        $user = null;
        if (!empty($validated['phone'])) {
            $user = User::where('phone', $validated['phone'])->first();
        } elseif (!empty($validated['national_id'])) {
            $user = User::where('national_id', $validated['national_id'])->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الجوال أو الهوية غير مسجل',
            ], 404);
        }

        $otpCode = (string) random_int(100000, 999999);
        $user->forceFill([
            'otp_code' => $otpCode,
            'otp_expiry' => now()->addMinutes(5),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رمز التحقق',
            'data' => [
                'otp_code' => $otpCode, // للتجربة فقط - احذفه في الإنتاج
            ],
        ]);
    }

    /**
     * POST /api/portallogistice/verify-otp
     * Body: phone, otp (or national_id, otp)
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required_without:national_id|string|nullable',
            'national_id' => 'required_without:phone|string|nullable',
            'otp' => 'required|string|size:6',
        ]);

        $user = null;
        if (!empty($validated['phone'])) {
            $user = User::where('phone', $validated['phone'])->first();
        } elseif (!empty($validated['national_id'])) {
            $user = User::where('national_id', $validated['national_id'])->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح أو منتهي',
            ], 400);
        }

        if ($user->otp_code !== $validated['otp']) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح',
            ], 400);
        }

        if ($user->otp_expiry && $user->otp_expiry->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'انتهت صلاحية رمز التحقق',
            ], 400);
        }

        $user->forceFill([
            'otp_code' => null,
            'otp_expiry' => null,
            'is_first_login' => false,
            'is_verified' => true,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'تم التحقق بنجاح',
        ]);
    }

    /**
     * POST /api/portallogistice/reset-password
     * Body: phone (required), password (required), password_confirmation (optional for confirmed rule)
     * Always returns JSON. No view().
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'phone.required' => 'The phone field is required.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 6 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $user = User::where('phone', $validated['phone'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'is_first_login' => false,
            'is_verified' => true,
            'otp_code' => null,
            'otp_expiry' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful',
        ]);
    }

    /**
     * POST /api/portallogistice/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->forceFill(['api_token' => null])->save();
        }
        return response()->json(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);
    }

    /**
     * POST /api/portallogistice/admin/logout
     */
    public function adminLogout(Request $request): JsonResponse
    {
        return $this->logout($request);
    }
}
