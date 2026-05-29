<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisitTypeSeeder extends Seeder
{
    private array $defaults = [
        ['name' => 'General OPD', 'slug' => 'general_opd', 'sort_order' => 1],
        ['name' => 'Pediatric',   'slug' => 'pediatric',   'sort_order' => 2],
        ['name' => 'Pregnant',    'slug' => 'pregnant',    'sort_order' => 3],
        ['name' => 'Eye',         'slug' => 'eye',         'sort_order' => 4],
        ['name' => 'Dental',      'slug' => 'dental',      'sort_order' => 5],
        ['name' => 'ENT',         'slug' => 'ent',         'sort_order' => 6],
    ];

    public function run(): void
    {
        $now = now();
        $tenants = DB::table('tenants')->pluck('id');

        foreach ($tenants as $tenantId) {
            foreach ($this->defaults as $type) {
                DB::table('visit_types')->upsert(
                    [
                        'id'         => Str::uuid()->toString(),
                        'tenant_id'  => $tenantId,
                        'name'       => $type['name'],
                        'slug'       => $type['slug'],
                        'is_default' => true,
                        'is_active'  => true,
                        'sort_order' => $type['sort_order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['tenant_id', 'slug'],
                    ['name', 'is_default', 'is_active', 'sort_order', 'updated_at']
                );
            }
        }
    }
}
