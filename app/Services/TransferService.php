// app/Services/TransferService.php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferService
{
    protected $walletService;
    protected $oneLinkService;
    protected $auditService;

    public function __construct(
        WalletService $walletService,
        OneLinkService $oneLinkService,
        AuditService $auditService
    ) {
        $this->walletService = $walletService;
        $this->oneLinkService = $oneLinkService;
        $this->auditService = $auditService;
    }

    /**
     * P2P Transfer with rate limiting
     */
    public function p2pTransfer(User $sender, string $receiverPhone, float $amount, string $pin): Transaction
    {
        // Validate PIN
        if (!password_verify($pin, $sender->pin)) {
            throw new \Exception('Invalid PIN');
        }

        // Rate limiting
        $this->checkRateLimit($sender->id, 'p2p_transfer');

        // Find receiver
        $receiver = User::where('phone', $receiverPhone)->first();
        if (!$receiver) {
            throw new \Exception('Receiver not found');
        }

        if ($receiver->id === $sender->id) {
            throw new \Exception('Cannot transfer to yourself');
        }

        if (!$receiver->isActive()) {
            throw new \Exception('Receiver account is inactive');
        }

        if (!$receiver->isKycApproved()) {
            throw new \Exception('Receiver KYC not approved');
        }

        // Process transfer
        $transaction = $this->walletService->transfer($sender, $receiver, $amount);

        // Log audit
        $this->auditService->log(
            $sender->id,
            'p2p_transfer',
            'wallet',
            [
                'receiver_id' => $receiver->id,
                'receiver_phone' => $receiver->phone,
                'amount' => $amount,
                'transaction_id' => $transaction->transaction_id
            ]
        );

        // Increment rate limit counter
        $this->incrementRateLimit($sender->id, 'p2p_transfer');

        return $transaction;
    }

    /**
     * Bank Transfer (IBFT)
     */
    public function bankTransfer(User $user, array $bankDetails, float $amount, string $pin): Transaction
    {
        // Validate PIN
        if (!password_verify($pin, $user->pin)) {
            throw new \Exception('Invalid PIN');
        }

        // Rate limiting
        $this->checkRateLimit($user->id, 'bank_transfer');

        // Initiate OneLink transfer
        $response = $this->oneLinkService->initiateTransfer([
            'account_number' => $bankDetails['account_number'],
            'bank_code' => $bankDetails['bank_code'],
            'amount' => $amount,
            'reference' => $bankDetails['reference'] ?? 'PAKPAISA-TRANSFER',
        ]);

        if ($response['status'] !== 'success') {
            throw new \Exception($response['message'] ?? 'Bank transfer failed');
        }

        // Deduct amount from wallet
        $transaction = $this->walletService->deductMoney(
            $user,
            $amount,
            'bank_transfer',
            [
                'bank_details' => $bankDetails,
                'bank_response' => $response
            ]
        );

        // Update transaction with bank reference
        $transaction->update([
            'reference_id' => $response['reference_number'],
            'metadata' => array_merge($transaction->metadata ?? [], [
                'bank_reference' => $response['bank_response']['bank_reference'] ?? null,
                'settlement_date' => $response['bank_response']['settlement_date'] ?? null,
            ])
        ]);

        // Log audit
        $this->auditService->log(
            $user->id,
            'bank_transfer',
            'wallet',
            [
                'amount' => $amount,
                'transaction_id' => $transaction->transaction_id,
                'bank_account' => $bankDetails['account_number'],
                'bank_name' => $bankDetails['bank_name']
            ]
        );

        $this->incrementRateLimit($user->id, 'bank_transfer');

        return $transaction;
    }

    /**
     * Bill Payment (Mock)
     */
    public function billPayment(User $user, array $billDetails, float $amount, string $pin): Transaction
    {
        // Validate PIN
        if (!password_verify($pin, $user->pin)) {
            throw new \Exception('Invalid PIN');
        }

        // Rate limiting
        $this->checkRateLimit($user->id, 'bill_payment');

        // Mock bill payment
        $response = $this->mockBillPayment($billDetails);

        if (!$response['success']) {
            throw new \Exception($response['message']);
        }

        // Deduct amount from wallet
        $transaction = $this->walletService->deductMoney(
            $user,
            $amount,
            'bill_payment',
            [
                'bill_details' => $billDetails,
                'bill_response' => $response
            ]
        );

        $this->auditService->log(
            $user->id,
            'bill_payment',
            'wallet',
            [
                'amount' => $amount,
                'transaction_id' => $transaction->transaction_id,
                'bill_type' => $billDetails['type'],
                'consumer_id' => $billDetails['consumer_id']
            ]
        );

        $this->incrementRateLimit($user->id, 'bill_payment');

        return $transaction;
    }

    /**
     * Mock bill payment
     */
    protected function mockBillPayment(array $billDetails): array
    {
        // Simulate API call
        sleep(1);

        $success = rand(1, 100) <= 95;

        return [
            'success' => $success,
            'message' => $success ? 'Bill paid successfully' : 'Bill payment failed',
            'reference' => 'BILL' . time() . rand(1000, 9999),
            'bill_type' => $billDetails['type'] ?? 'electricity',
            'consumer_id' => $billDetails['consumer_id'],
        ];
    }

    /**
     * Check rate limit
     */
    protected function checkRateLimit($userId, $action): void
    {
        $key = "rate_limit:{$userId}:{$action}";
        $count = Cache::get($key, 0);

        if ($count >= 10) {
            throw new \Exception('Rate limit exceeded. Please try again later.');
        }
    }

    /**
     * Increment rate limit counter
     */
    protected function incrementRateLimit($userId, $action): void
    {
        $key = "rate_limit:{$userId}:{$action}";
        Cache::increment($key);
        Cache::expire($key, 600); // 10 minutes
    }
}
