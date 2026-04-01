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

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello API', 'time' => now()->toDateTimeString()]);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/webhook/sadq', [SadqWebhookController::class, 'handle']);
Route::post('/sadq/webhook', [SadqWebhookController::class, 'handle']);
Route::get('/sadq/webhook', function () {
    return response()->json(['success' => false, 'message' => 'Method Not Allowed. Use POST.'], 405);
});

Route::middleware('auth.token')->group(function () {
    Route::get('contracts', [ContractController::class, 'index']);
    Route::get('contracts/{id}', [ContractController::class, 'show']);
    Route::post('contracts/{id}/nafath', [ContractController::class, 'nafath']);
    Route::get('portallogistice/contracts', [ContractController::class, 'index']);
    Route::get('portallogistice/contracts/{id}', [ContractController::class, 'show']);
    Route::post('portallogistice/contracts/{id}/nafath', [ContractController::class, 'nafath']);
        Route::post('portallogistice/contracts/{id}/payment-receipt', [ContractController::class, 'updatePaymentReceipt']);

});

Route::middleware(['auth.token', 'admin'])->group(function () {
    Route::get('admin/users', [AdminUserController::class, 'index']);
    Route::post('admin/users', [AdminUserController::class, 'store']);
    Route::post('contracts', [ContractController::class, 'store']);
    Route::post('contracts/{id}/send', [ContractController::class, 'send']);
    Route::post('contracts/{id}/admin-approve', [ContractController::class, 'adminApprove']);
    Route::post('contracts/{id}/reject', [ContractController::class, 'reject']);
    Route::post('portallogistice/admin/contracts', [ContractController::class, 'store']);
    Route::post('portallogistice/admin/contracts/{id}/send', [ContractController::class, 'send']);
    Route::post('portallogistice/admin/contracts/{id}/admin-approve', [ContractController::class, 'adminApprove']);
    Route::post('portallogistice/admin/contracts/{id}/reject', [ContractController::class, 'reject']);
});

Route::prefix('portallogistice')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('send-otp', [AuthController::class, 'sendOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('admin/login', [AuthController::class, 'adminLogin']);
    Route::post('admin/register', [AdminUserController::class, 'registerAdmin']);

    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('auth.token');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth.token');
    Route::post('nafath/initiate', [NafathController::class, 'initiate'])->middleware('auth.token');
    Route::get('nafath/checkStatus', [NafathController::class, 'checkStatus'])->middleware('auth.token');

    Route::prefix('admin')->middleware(['auth.token', 'admin'])->group(function () {
        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users', [AdminUserController::class, 'store']);
        Route::post('logout', [AuthController::class, 'adminLogout']);
    });
});

Route::prefix('portallogistice')->middleware('auth.token')->group(function () {
    Route::get('profile', function(\Illuminate\Http\Request $r) {
        return response()->json(['success' => true, 'data' => ['user' => $r->user()->toApiArray()]]);
    });
    Route::put('profile', function(\Illuminate\Http\Request $r) {
        $user = $r->user();
        $data = array_filter([
            'first_name'       => $r->input('first_name'),
            'last_name'        => $r->input('family_name') ?? $r->input('last_name'),
            'name'             => $r->input('first_name') ?? $r->input('name'),
            'phone'            => $r->input('phone'),
            'email'            => $r->input('email') ?: null,
            'national_id'      => $r->input('national_id'),
            'birth_date'       => $r->input('birth_date'),
            'iban'             => $r->input('iban'),
            'bank_name'        => $r->input('bank_name'),
            'father_name'      => $r->input('father_name'),
            'grandfather_name' => $r->input('grandfather_name'),
            'region'           => $r->input('region'),
        ], fn($v) => $v !== null);
        $user->forceFill($data)->save();
        return response()->json(['success' => true, 'data' => ['user' => $user->fresh()->toApiArray()]]);
    });
});

Route::prefix('portallogistice')->middleware('auth.token')->group(function () {
    Route::match(['put','patch'], 'profile/update', function(\Illuminate\Http\Request $r) {
        $user = $r->user();
        $data = array_filter([
            'first_name'       => $r->input('first_name'),
            'last_name'        => $r->input('family_name') ?? $r->input('last_name'),
            'name'             => $r->input('first_name') ?? $r->input('name'),
            'phone'            => $r->input('phone'),
            'email'            => $r->input('email') ?: null,
            'national_id'      => $r->input('national_id'),
            'birth_date'       => $r->input('birth_date'),
            'iban'             => $r->input('iban'),
            'bank_name'        => $r->input('bank_name'),
            'father_name'      => $r->input('father_name'),
            'grandfather_name' => $r->input('grandfather_name'),
            'region'           => $r->input('region'),
        ], fn($v) => $v !== null);
        $user->forceFill($data)->save();
        return response()->json(['success' => true, 'data' => ['user' => $user->toApiArray()]]);
    });
});
