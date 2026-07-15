// app/Models/Wallet.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'balance', 'pending_balance',
        'currency', 'wallet_id', 'is_active'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Helper Methods
    public function addBalance($amount): void
    {
        $this->balance = bcadd($this->balance, $amount, 2);
        $this->save();
    }

    public function deductBalance($amount): void
    {
        $this->balance = bcsub($this->balance, $amount, 2);
        $this->save();
    }

    public function hasSufficientBalance($amount): bool
    {
        return bccomp($this->balance, $amount, 2) >= 0;
    }

    public function calculateFee($amount): float
    {
        $fee = bcmul($amount, '0.01', 2);
        $minFee = 10.00;
        return max($fee, $minFee);
    }
}
