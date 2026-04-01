<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ContractController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Production fallback routes for API bridge edge cases on shared hosting.
Route::prefix('api')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->group(function () {
        Route::middleware(['auth.token', 'admin'])->group(function () {
            Route::get('admin/users', [AdminUserController::class, 'index']);
            Route::post('admin/users', [AdminUserController::class, 'store']);

            Route::get('portallogistice/admin/users', [AdminUserController::class, 'index']);
            Route::post('portallogistice/admin/users', [AdminUserController::class, 'store']);

            Route::post('contracts', [ContractController::class, 'store']);
            Route::post('contracts/{id}/send', [ContractController::class, 'send']);
            Route::post('contracts/{id}/admin-approve', [ContractController::class, 'adminApprove']);
            Route::post('contracts/{id}/reject', [ContractController::class, 'reject']);

            Route::post('portallogistice/admin/contracts', [ContractController::class, 'store']);
            Route::post('portallogistice/admin/contracts/{id}/send', [ContractController::class, 'send']);
            Route::post('portallogistice/admin/contracts/{id}/admin-approve', [ContractController::class, 'adminApprove']);
            Route::post('portallogistice/admin/contracts/{id}/reject', [ContractController::class, 'reject']);
        });

        Route::middleware('auth.token')->group(function () {
            Route::get('contracts', [ContractController::class, 'index']);
            Route::get('contracts/{id}', [ContractController::class, 'show']);
            Route::post('contracts/{id}/nafath', [ContractController::class, 'nafath']);

            Route::get('portallogistice/contracts', [ContractController::class, 'index']);
            Route::get('portallogistice/contracts/{id}', [ContractController::class, 'show']);
            Route::post('portallogistice/contracts/{id}/nafath', [ContractController::class, 'nafath']);
        });
    });
