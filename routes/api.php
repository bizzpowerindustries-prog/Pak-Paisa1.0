// routes/api.php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\WalletController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Wallet
    Route::get('/balance', [TransferController::class, 'balance']);
    Route::get('/transactions', [TransferController::class, 'history']);
    Route::get('/transactions/{transactionId}', [TransferController::class, 'transactionDetails']);
    
    // Transfers
    Route::post('/transfer/p2p', [TransferController::class, 'p2pTransfer']);
    Route::post('/transfer/bank', [TransferController::class, 'bankTransfer']);
    Route::post('/transfer/bill', [TransferController::class, 'billPayment']);
    
    // KYC
    Route::post('/kyc/upload', [AuthController::class, 'uploadKyc']);
    Route::get('/kyc/status', [AuthController::class, 'kycStatus']);
    
    // Profile
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/change-password', [AuthController::class, 'changePassword']);
    Route::put('/profile/change-pin', [AuthController::class, 'changePin']);
    Route::post('/profile/enable-2fa', [AuthController::class, 'enableTwoFactor']);
    Route::post('/profile/disable-2fa', [AuthController::class, 'disableTwoFactor']);
});
