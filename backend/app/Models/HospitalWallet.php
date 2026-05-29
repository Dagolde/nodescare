<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HospitalWallet extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'tenant_id', 'balance', 'low_balance_threshold_percent',
        'initial_funded_amount', 'is_active',
    ];

    protected $casts = [
        'balance'                       => 'decimal:2',
        'low_balance_threshold_percent' => 'decimal:2',
        'initial_funded_amount'         => 'decimal:2',
        'is_active'                     => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    /**
     * Check if balance is at or below the low-balance threshold.
     */
    public function isLowBalance(): bool
    {
        if ($this->initial_funded_amount <= 0) {
            return false;
        }

        $percentRemaining = ($this->balance / $this->initial_funded_amount) * 100;
        return $percentRemaining <= $this->low_balance_threshold_percent;
    }

    /**
     * Get balance as percentage of initial funded amount.
     */
    public function balancePercent(): float
    {
        if ($this->initial_funded_amount <= 0) {
            return 100;
        }
        return round(($this->balance / $this->initial_funded_amount) * 100, 2);
    }
}
