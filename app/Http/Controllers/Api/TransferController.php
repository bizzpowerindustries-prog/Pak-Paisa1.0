// app/Http/Controllers/Api/TransferController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransferController extends Controller
{
    protected $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * P2P Transfer
     */
    public function p2pTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_phone' => 'required|string|regex:/^03[0-9]{9}$/',
            'amount' => 'required|numeric|min:10',
            'pin' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            // Check KYC
            if (!$user->isKycApproved()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'KYC verification required for transfers'
                ], 403);
            }

            // Check balance
            if ($user->wallet->balance < $request->amount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient balance'
                ], 400);
            }

            $transaction = $this->transferService->p2pTransfer(
                $user,
                $request->receiver_phone,
                $request->amount,
                $request->pin
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Transfer completed successfully',
                'data' => [
                    'transaction' => $transaction,
                    'new_balance' => $user->fresh()->wallet->balance,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Bank Transfer (IBFT)
     */
    public function bankTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_number' => 'required|string',
            'bank_code' => 'required|string',
            'bank_name' => 'required|string',
            'amount' => 'required|numeric|min:100',
            'pin' => 'required|string|size:4',
            'reference' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            if (!$user->isKycApproved()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'KYC verification required for bank transfers'
                ], 403);
            }

            if ($user->wallet->balance < $request->amount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient balance'
                ], 400);
            }

            $transaction = $this->transferService->bankTransfer(
                $user,
                [
                    'account_number' => $request->account_number,
                    'bank_code' => $request->bank_code,
                    'bank_name' => $request->bank_name,
                    'reference' => $request->reference,
                ],
                $request->amount,
                $request->pin
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bank transfer initiated successfully',
                'data' => [
                    'transaction' => $transaction,
                    'new_balance' => $user->fresh()->wallet->balance,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Bill Payment
     */
    public function billPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:electricity,gas,water,internet',
            'consumer_id' => 'required|string',
            'amount' => 'required|numeric|min:50',
            'pin' => 'required|string|size:4',
            'company' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            if (!$user->isKycApproved()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'KYC verification required for bill payments'
                ], 403);
            }

            if ($user->wallet->balance < $request->amount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient balance'
                ], 400);
            }

            $transaction = $this->transferService->billPayment(
                $user,
                [
                    'type' => $request->type,
                    'consumer_id' => $request->consumer_id,
                    'company' => $request->company ?? 'Unknown',
                ],
                $request->amount,
                $request->pin
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bill payment completed successfully',
                'data' => [
                    'transaction' => $transaction,
                    'new_balance' => $user->fresh()->wallet->balance,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get transaction history
     */
    public function history(Request $request)
    {
        $user = $request->user();
        
        $transactions = $user->transactions()
            ->with(['wallet'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ]);
    }

    /**
     * Get transaction details
     */
    public function transactionDetails(Request $request, $transactionId)
    {
        $user = $request->user();
        
        $transaction = $user->transactions()
            ->where('transaction_id', $transactionId)
            ->with(['wallet'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $transaction
        ]);
    }

    /**
     * Get balance
     */
    public function balance(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'balance' => $user->wallet->balance,
                'pending_balance' => $user->wallet->pending_balance,
                'currency' => $user->wallet->currency,
                'wallet_id' => $user->wallet->wallet_id,
            ]
        ]);
    }
}
