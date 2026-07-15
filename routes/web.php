// routes/web.php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\SettlementController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/wallet', [DashboardController::class, 'wallet'])->name('wallet');
    Route::get('/transfer', [DashboardController::class, 'transfer'])->name('transfer');
    Route::get('/transactions', [DashboardController::class, 'transactions'])->name('transactions');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::post('/users/{id}/approve', [AdminController::class, 'approveKyc'])->name('admin.approve-kyc');
    Route::post('/users/{id}/block', [AdminController::class, 'blockUser'])->name('admin.block-user');
    Route::get('/transactions', [AdminController::class, 'transactions'])->name('admin.transactions');
    Route::post('/users/{id}/add-balance', [AdminController::class, 'addBalance'])->name('admin.add-balance');
    Route::get('/settlement', [SettlementController::class, 'index'])->name('admin.settlement');
    Route::get('/settlement/export', [SettlementController::class, 'export'])->name('admin.settlement.export');
});
