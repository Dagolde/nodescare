<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;
use App\Models\User;

class InvoicePayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'invoice_id', 'amount', 'payment_method', 'reference', 'notes', 'accountant_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function accountant()
    {
        return $this->belongsTo(User::class, 'accountant_id');
    }
}
