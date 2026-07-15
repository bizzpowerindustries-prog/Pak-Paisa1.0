// app/Models/User.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'cnic', 'password', 'pin',
        'two_factor_secret', 'two_factor_enabled',
        'kyc_status', 'status'
    ];

    protected $hidden = [
        'password', 'pin', 'two_factor_secret',
        'remember_token', 'phone_encrypted', 'cnic_encrypted'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
    ];

    // Relations
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function kycDocuments()
    {
        return $this->hasMany(KycDocument::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    // Accessors
    public function getPhoneAttribute($value)
    {
        return decrypt($this->phone_encrypted);
    }

    public function getCnicAttribute($value)
    {
        return decrypt($this->cnic_encrypted);
    }

    // Mutators
    public function setPhoneAttribute($value)
    {
        $this->attributes['phone_encrypted'] = encrypt($value);
        $this->attributes['phone'] = $value;
    }

    public function setCnicAttribute($value)
    {
        $this->attributes['cnic_encrypted'] = encrypt($value);
        $this->attributes['cnic'] = $value;
    }

    // Helper Methods
    public function isKycApproved(): bool
    {
        return $this->kyc_status === 'approved';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canAddDevice(): bool
    {
        return $this->devices()->where('is_active', true)->count() < 3;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && !empty($this->two_factor_secret);
    }

    public function getBalanceAttribute(): float
    {
        return $this->wallet ? $this->wallet->balance : 0;
    }
}
