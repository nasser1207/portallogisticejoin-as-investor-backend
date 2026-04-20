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
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvestorRequestController;

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
     Route::post('contracts/{id}/payment-receipt', [ContractController::class, 'updatePaymentReceipt']);
    Route::get('portallogistice/contracts', [ContractController::class, 'index']);
    Route::get('contracts/{id}/payments', [ContractController::class, 'payments']);
Route::get('portallogistice/contracts/{id}/payments', [ContractController::class, 'payments']);
    Route::get('portallogistice/contracts/{id}', [ContractController::class, 'show']);
    Route::post('portallogistice/contracts/{id}/nafath', [ContractController::class, 'nafath']);
        Route::post('portallogistice/investor-requests/{id}/nafath', [InvestorRequestController::class, 'nafath']);
        
         Route::post('investor-requests/{id}/nafath', [InvestorRequestController::class, 'nafath']);

        Route::post('portallogistice/contracts/{id}/payment-receipt', [ContractController::class, 'updatePaymentReceipt']);
    Route::post('contracts/{id}/payment-receipt', [ContractController::class, 'updatePaymentReceipt']);
    Route::post('contracts/{id}/upload-sale-receipt',             [ContractController::class, 'uploadSaleReceipt']);
Route::post('portallogistice/contracts/{id}/upload-sale-receipt', [ContractController::class, 'uploadSaleReceipt']);

});

Route::middleware(['auth.token', 'admin'])->group(function () {
   Route::get('admin/users',                    [AdminUserController::class, 'index']);
    Route::post('admin/users',                   [AdminUserController::class, 'store']);
    
    Route::get('admin/users/{identifier}',       [AdminUserController::class, 'adminShowUser']);
    Route::put('admin/users/{identifier}',       [AdminUserController::class, 'adminUpdateUser']);
    Route::post('admin/users/{id}/activate',     [AdminUserController::class, 'adminActivateUser']);
    Route::post('admin/users/{id}/deactivate',   [AdminUserController::class, 'adminDeactivateUser']);
    
    Route::get('admin/invoices',                [InvoiceController::class, 'adminIndex']);
Route::get('portallogistice/admin/invoices',[InvoiceController::class, 'adminIndex']);
Route::post('admin/invoices/{id}/approve',  [InvoiceController::class, 'approve']);
Route::post('admin/invoices/{id}/reject',   [InvoiceController::class, 'reject']);
Route::post('portallogistice/admin/invoices/{id}/approve', [InvoiceController::class, 'approve']);
Route::post('portallogistice/admin/invoices/{id}/reject',  [InvoiceController::class, 'reject']);
    
     Route::get('portallogistice/admin/users/{identifier}',     [AdminUserController::class, 'adminShowUser']);
    Route::put('portallogistice/admin/users/{identifier}',     [AdminUserController::class, 'adminUpdateUser']);
    Route::post('portallogistice/admin/users/{id}/activate',   [AdminUserController::class, 'adminActivateUser']);
    Route::post('portallogistice/admin/users/{id}/deactivate', [AdminUserController::class, 'adminDeactivateUser']);  
    
    Route::post('contracts', [ContractController::class, 'store']);
    Route::post('contracts/{id}/send', [ContractController::class, 'send']);
    Route::post('contracts/{id}/admin-approve', [ContractController::class, 'adminApprove']);
    Route::post('contracts/{id}/reject', [ContractController::class, 'reject']);
    Route::post('portallogistice/admin/contracts', [ContractController::class, 'store']);
    Route::post('portallogistice/admin/contracts/{id}/send', [ContractController::class, 'send']);
    Route::post('portallogistice/admin/contracts/{id}/admin-approve', [ContractController::class, 'adminApprove']);
    Route::post('portallogistice/admin/contracts/{id}/reject', [ContractController::class, 'reject']);

    Route::get('admin/requests',                                   [InvestorRequestController::class, 'adminIndex']);
    Route::get('portallogistice/admin/requests',                   [InvestorRequestController::class, 'adminIndex']);
    Route::post('admin/requests/{id}/approve',                     [InvestorRequestController::class, 'approve']);
    Route::post('admin/requests/{id}/reject',                      [InvestorRequestController::class, 'reject']);
    Route::post('admin/requests/{id}/send-whatsapp',               [InvestorRequestController::class, 'sendWhatsapp']);
    Route::post('admin/requests/{id}/deploy-contract',             [InvestorRequestController::class, 'deployContract']);
    Route::post('portallogistice/admin/requests/{id}/approve',         [InvestorRequestController::class, 'approve']);
    Route::post('portallogistice/admin/requests/{id}/reject',          [InvestorRequestController::class, 'reject']);
    Route::post('portallogistice/admin/requests/{id}/send-whatsapp',   [InvestorRequestController::class, 'sendWhatsapp']);
    Route::post('portallogistice/admin/requests/{id}/deploy-contract', [InvestorRequestController::class, 'deployContract']);









    
    Route::get('admin/payments',                           [PaymentController::class, 'index']);
Route::get('portallogistice/admin/payments',           [PaymentController::class, 'index']);
 
// Admin: upload receipt for a single payment row → marks it as received
Route::post('admin/payments/{id}/receipt',             [PaymentController::class, 'uploadReceipt']);
Route::post('portallogistice/admin/payments/{id}/receipt', [PaymentController::class, 'uploadReceipt']);

Route::post('admin/contracts/{id}/review-payment',             [ContractController::class, 'reviewPayment']);
Route::post('portallogistice/admin/contracts/{id}/review-payment', [ContractController::class, 'reviewPayment']);
 
});

Route::prefix('portallogistice')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('send-otp', [AuthController::class, 'sendOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('admin/login', [AuthController::class, 'adminLogin']);
    Route::post('admin/register', [AdminUserController::class, 'registerAdmin']);

    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('auth.token');
     Route::get('analytics/summary',  [AnalyticsController::class, 'summary'])->middleware('auth.token');
    Route::get('analytics/payments', [AnalyticsController::class, 'payments'])->middleware('auth.token');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth.token');
    Route::post('nafath/initiate', [NafathController::class, 'initiate'])->middleware('auth.token');
    Route::get('nafath/checkStatus', [NafathController::class, 'checkStatus'])->middleware('auth.token');

    Route::get('invoices',                      [InvoiceController::class, 'userIndex'])->middleware('auth.token');
    Route::post('invoices/{id}/receipt',        [InvoiceController::class, 'uploadReceipt'])->middleware('auth.token');
  
    
    Route::prefix('admin')->middleware(['auth.token', 'admin'])->group(function () {
        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users', [AdminUserController::class, 'store']);
        Route::post('logout', [AuthController::class, 'adminLogout']);
      
    });
});
    
Route::prefix('portallogistice')->middleware('auth.token')->group(function () {
    Route::get('requests',  [InvestorRequestController::class, 'userIndex'])->middleware('auth.token');
Route::post('requests', [InvestorRequestController::class, 'store'])->middleware('auth.token');
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

Route::prefix('portallogistice')->middleware('auth.token')->group(function () {
    Route::get('notifications', [App\Http\Controllers\NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllRead']);
});
