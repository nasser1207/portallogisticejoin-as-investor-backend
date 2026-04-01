<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NafathController;
use App\Http\Controllers\SadqTestController;
use App\Http\Controllers\SadqWebhookController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\Admin\UserController as AdminUserController;

/*
|--------------------------------------------------------------------------
| Step 2 – Learning routes: first JSON responses (GET, no database)
|--------------------------------------------------------------------------
*/
Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello API',
        'time' => now()->toDateTimeString(),
    ]);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

/*
| Sadq webhooks — Sadq calls POST /api/sadq/webhook (see config services.sadq.webhook_url).
| Keep legacy POST /api/webhook/sadq for backward compatibility.
*/
Route::post('/webhook/sadq', [SadqWebhookController::class, 'handle']);
Route::post('/sadq/webhook', [SadqWebhookController::class, 'handle']);
// Browser / health check: must not 404; POST is the real webhook.
Route::get('/sadq/webhook', function () {
    return response()->json([
        'success' => false,
        'message' => 'Method Not Allowed. Use POST.',
    ], 405);
});

Route::post('test/nafath', [SadqTestController::class, 'nafath']);

Route::middleware('auth.token')->group(function () {
    // User + admin list; admin gets all, user gets own contracts.
    Route::get('contracts', [ContractController::class, 'index']);
    Route::get('contracts/{id}', [ContractController::class, 'show']);
    Route::post('contracts/{id}/nafath', [ContractController::class, 'nafath']);

    // Frontend compatibility aliases.
    Route::get('portallogistice/contracts', [ContractController::class, 'index']);
    Route::get('portallogistice/contracts/{id}', [ContractController::class, 'show']);
    Route::post('portallogistice/contracts/{id}/nafath', [ContractController::class, 'nafath']);
});

Route::middleware(['auth.token', 'admin'])->group(function () {
    Route::post('contracts', [ContractController::class, 'store']);
    Route::post('contracts/{id}/send', [ContractController::class, 'send']);
    Route::post('contracts/{id}/admin-approve', [ContractController::class, 'adminApprove']);
    Route::post('contracts/{id}/reject', [ContractController::class, 'reject']);

    // Frontend compatibility aliases.
    Route::post('portallogistice/admin/contracts', [ContractController::class, 'store']);
    Route::post('portallogistice/admin/contracts/{id}/send', [ContractController::class, 'send']);
    Route::post('portallogistice/admin/contracts/{id}/admin-approve', [ContractController::class, 'adminApprove']);
    Route::post('portallogistice/admin/contracts/{id}/reject', [ContractController::class, 'reject']);
});

/*
|--------------------------------------------------------------------------
| API Routes - Portallogistice (compatible with React frontend)
|--------------------------------------------------------------------------
*/

Route::prefix('portallogistice')->group(function () {

    // ---- Public auth ----
    Route::post('login', [AuthController::class, 'login']);
    Route::post('send-otp', [AuthController::class, 'sendOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    // Admin login + public register (أول مرة - إنشاء حساب مدير بدون تسجيل دخول)
    Route::post('admin/login', [AuthController::class, 'adminLogin']);
    Route::post('admin/register', [AdminUserController::class, 'registerAdmin']);

    // ---- Protected (user) - dashboard + logout ----
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('auth.token');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth.token');

    // Sadq Nafath (Bearer token required — same as ContractForm.js)
    Route::post('nafath/initiate', [NafathController::class, 'initiate'])->middleware('auth.token');
    Route::get('nafath/checkStatus', [NafathController::class, 'checkStatus'])->middleware('auth.token');

    // ---- Protected (admin only) ----
    Route::prefix('admin')->middleware(['auth.token', 'admin'])->group(function () {
        Route::post('logout', [AuthController::class, 'adminLogout']);
        Route::post('users', [AdminUserController::class, 'store']);
    });
});
