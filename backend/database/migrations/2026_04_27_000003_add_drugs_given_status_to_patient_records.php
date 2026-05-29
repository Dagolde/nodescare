<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires modifying the ENUM column to add the new value
        DB::statement("ALTER TABLE patient_records MODIFY COLUMN status ENUM(
            'pending_payment', 'active', 'in_transit', 'admitted', 'discharged', 'completed', 'drugs_given'
        ) NOT NULL DEFAULT 'pending_payment'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE patient_records MODIFY COLUMN status ENUM(
            'pending_payment', 'active', 'in_transit', 'admitted', 'discharged', 'completed'
        ) NOT NULL DEFAULT 'pending_payment'");
    }
};
