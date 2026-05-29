<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class PatientDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'patient_id', 'document_type', 'object_key', 'original_filename', 'uploader_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
