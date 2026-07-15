// app/Services/WalletService.php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    /**
     * Create wallet for user
     */
    public function createWallet(User $user, $currency = 'PKR'): Wallet
    {
        return Wallet::create([
            'user_id' => $user->id,
            'wallet_id' => 'PKW' . str_pad($user->id, 8, '0', STR_PAD_LEFT),
            'currency' => $currency,
            'balance' => 0,
            'pending_balance' => 0,
        ]);
    }

    /**
     * Get wallet balance with lock
     */
    public function getBalanceWithLock(int $walletId): Wallet
    {
        return Wallet::where('id', $walletId)->lockForUpdate()->first();
    }

    /**
     * Add money to wallet
     */
    public function addMoney(User $user, $amount, $referenceId = null, $metadata = []): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $referenceId, $metadata) {
            $wallet = $this->getBalanceWithLock($user->wallet->id);
            
            // Create transaction
            $transaction = Transaction::create([
                'transaction_id' => 'TXN' . time() . rand(1000, 9999),
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'status' => 'success',
                'amount' => $amount,
                'fee' => 0,
                'net_amount' => $amount,
                'balance_before' => $wallet->balance,
                'balance_after' => bcadd($wallet->balance, $amount, 2),
                'metadata' => $metadata,
                'reference_id' => $referenceId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'completed_at' => now(),
            ]);

            // Update wallet
            $wallet->addBalance($amount);

            Log::info('Money added to wallet', [
                'user_id' => $user->id,
                'amount' => $amount,
                'transaction_id' => $transaction->transaction_id
            ]);

            return $transaction;
        });
    }

    /**
     * Deduct money from wallet with lock
     */
    public function deductMoney(User $user, $amount, $type, $metadata = []): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $metadata) {
            $wallet = $this->getBalanceWithLock($user->wallet->id);
            
            if (!$wallet->hasSufficientBalance($amount)) {
                throw new \Exception('Insufficient balance');
            }

            $fee = $wallet->calculateFee($amount);
            $netAmount = bcsub($amount, $fee, 2);

            // Create transaction
            $transaction = Transaction::create([
                'transaction_id' => 'TXN' . time() . rand(1000, 9999),
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => $type,
                'status' => 'success',
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'balance_before' => $wallet->balance,
                'balance_after' => bcsub($wallet->balance, $amount, 2),
                'metadata' => $metadata,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'completed_at' => now(),
            ]);

            // Update wallet
            $wallet->deductBalance($amount);

            Log::info('Money deducted from wallet', [
                'user_id' => $user->id,
                'amount' => $amount,
                'fee' => $fee,
                'transaction_id' => $transaction->transaction_id
            ]);

            return $transaction;
        });
    }

    /**
     * Transfer between wallets
     */
    public function transfer(User $sender, User $receiver, $amount): Transaction
    {
        return DB::transaction(function () use ($sender, $receiver, $amount) {
            // Deduct from sender
            $senderTransaction = $this->deductMoney(
                $sender,
                $amount,
                'transfer',
                ['receiver_id' => $receiver->id, 'receiver_phone' => $receiver->phone]
            );

            // Add to receiver
            $receiverTransaction = $this->addMoney(
                $receiver,
                $amount,
                $senderTransaction->transaction_id,
                ['sender_id' => $sender->id, 'sender_phone' => $sender->phone]
            );

            return $senderTransaction;
        });
    }
}
