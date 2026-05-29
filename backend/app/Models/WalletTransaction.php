<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'tenant_id', 'wallet_id', 'type', 'reason',
        'amount', 'balance_before', 'balance_after',
        'reference', 'description', 'patient_id', 'invoice_id', 'performed_by',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
    ];

    public function wallet()
    {
        return $this->belongsTo(HospitalWallet::class, 'wallet_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class)->withoutGlobalScope(\App\Models\Scopes\TenantScope::class);
    }
}
