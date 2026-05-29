<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class InvoiceItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'invoice_id', 'description', 'quantity', 'unit_price', 'total_price',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
