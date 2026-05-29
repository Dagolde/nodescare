<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WalletDeductionConfig extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'plan_id',
        'new_patient_card_fee', 'consultation_fee', 'pharmacy_per_transaction_fee',
        'deduct_on_new_patient', 'deduct_on_consultation', 'deduct_on_pharmacy',
    ];

    protected $casts = [
        'new_patient_card_fee'           => 'decimal:2',
        'consultation_fee'               => 'decimal:2',
        'pharmacy_per_transaction_fee'   => 'decimal:2',
        'deduct_on_new_patient'          => 'boolean',
        'deduct_on_consultation'         => 'boolean',
        'deduct_on_pharmacy'             => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the config for a given plan_id (falls back to global default).
     */
    public static function forPlan(?string $planId): self
    {
        if ($planId) {
            $config = self::where('plan_id', $planId)->first();
            if ($config) {
                return $config;
            }
        }

        // Fall back to global default (plan_id = null or empty string)
        $config = self::where(function ($q) {
            $q->whereNull('plan_id')->orWhere('plan_id', '');
        })->first();

        if ($config) {
            return $config;
        }

        // Return an unsaved default with zero fees if nothing configured
        return new self([
            'new_patient_card_fee'         => 0,
            'consultation_fee'             => 0,
            'pharmacy_per_transaction_fee' => 0,
            'deduct_on_new_patient'        => true,
            'deduct_on_consultation'       => true,
            'deduct_on_pharmacy'           => true,
        ]);
    }
}
