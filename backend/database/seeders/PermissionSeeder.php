<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Named permissions and their default role assignments.
     * Roles listed here receive the permission by default.
     */
    private array $permissions = [
        ['name' => 'patients.view',          'display_name' => 'View Patients',            'group' => 'patients',     'roles' => ['hospital_admin','doctor','front_desk_officer','accountant','pharmacist','lab_technician','nurse','triage','ward_staff','ward_nurse','department_staff']],
        ['name' => 'patients.create',        'display_name' => 'Register Patients',         'group' => 'patients',     'roles' => ['hospital_admin','front_desk_officer']],
        ['name' => 'patients.new_visit',     'display_name' => 'Create New Visit',          'group' => 'patients',     'roles' => ['hospital_admin','front_desk_officer']],
        ['name' => 'patients.anonymise',     'display_name' => 'Anonymise Patient Records', 'group' => 'patients',     'roles' => ['hospital_admin']],
        ['name' => 'documents.upload',       'display_name' => 'Upload Documents',          'group' => 'documents',    'roles' => ['hospital_admin','front_desk_officer','doctor','nurse']],
        ['name' => 'documents.view',         'display_name' => 'View Documents',            'group' => 'documents',    'roles' => ['hospital_admin','doctor','front_desk_officer','nurse','accountant']],
        ['name' => 'invoices.create',        'display_name' => 'Create Invoices',           'group' => 'invoices',     'roles' => ['hospital_admin','accountant']],
        ['name' => 'invoices.settle',        'display_name' => 'Settle Invoices',           'group' => 'invoices',     'roles' => ['hospital_admin','accountant']],
        ['name' => 'reports.view',           'display_name' => 'View Reports',              'group' => 'reports',      'roles' => ['hospital_admin','accountant']],
        ['name' => 'staff.manage',           'display_name' => 'Manage Staff',              'group' => 'staff',        'roles' => ['hospital_admin']],
        ['name' => 'departments.manage',     'display_name' => 'Manage Departments',        'group' => 'departments',  'roles' => ['hospital_admin']],
        ['name' => 'workflows.manage',       'display_name' => 'Manage Workflow Templates', 'group' => 'workflows',    'roles' => ['hospital_admin']],
        ['name' => 'audit_logs.view',        'display_name' => 'View Audit Logs',           'group' => 'audit',        'roles' => ['hospital_admin']],
        ['name' => 'department.queue.view',  'display_name' => 'View Department Queue',     'group' => 'department',   'roles' => ['hospital_admin','doctor','front_desk_officer','pharmacist','lab_technician','nurse','triage','ward_staff','ward_nurse','department_staff']],
        ['name' => 'patient_records.manage', 'display_name' => 'Manage Patient Records',   'group' => 'records',      'roles' => ['hospital_admin','doctor','pharmacist','lab_technician','nurse','triage','ward_staff','ward_nurse','department_staff']],
        ['name' => 'billing.pharmacy',       'display_name' => 'Create Pharmacy Bills',      'group' => 'billing',      'roles' => ['hospital_admin','pharmacist']],
        ['name' => 'billing.lab',            'display_name' => 'Create Lab Bills',           'group' => 'billing',      'roles' => ['hospital_admin','lab_technician']],
        ['name' => 'ward.manage',            'display_name' => 'Manage Ward',               'group' => 'ward',         'roles' => ['hospital_admin','ward_staff','ward_nurse','nurse','department_staff']],
        ['name' => 'accounts.queue.view',    'display_name' => 'View Accounts Queue',       'group' => 'accounts',     'roles' => ['hospital_admin','accountant']],
        ['name' => 'appointments.manage',    'display_name' => 'Manage Appointments',       'group' => 'appointments', 'roles' => ['hospital_admin','doctor','nurse','triage','front_desk_officer']],
        ['name' => 'my_department.manage',   'display_name' => 'Manage Own Department',     'group' => 'department',   'roles' => ['hospital_admin']],
        ['name' => 'visit_types.view',        'display_name' => 'View Visit Types',           'group' => 'visit_types',  'roles' => ['hospital_admin','front_desk_officer','doctor','nurse','triage']],
        ['name' => 'visit_types.manage',      'display_name' => 'Manage Visit Types',         'group' => 'visit_types',  'roles' => ['hospital_admin']],
        ['name' => 'doctors.list_by_visit_type', 'display_name' => 'List Doctors by Visit Type', 'group' => 'doctors',  'roles' => ['hospital_admin','front_desk_officer']],
    ];

    public function run(): void
    {
        $now = now();

        foreach ($this->permissions as $perm) {
            // Upsert permission
            DB::table('permissions')->upsert(
                [
                    'name'         => $perm['name'],
                    'display_name' => $perm['display_name'],
                    'group'        => $perm['group'],
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ],
                ['name'],
                ['display_name', 'group', 'updated_at']
            );

            // Insert role assignments (ignore duplicates)
            foreach ($perm['roles'] as $role) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role'       => $role,
                    'permission' => $perm['name'],
                    'created_at' => $now,
                ]);
            }
        }

        // Bust the Redis permission cache for all roles so the new assignments
        // take effect immediately without waiting for the 3600-second TTL to expire.
        try {
            $keys = \Illuminate\Support\Facades\Redis::keys('*:role_perms:*');
            if (!empty($keys)) {
                \Illuminate\Support\Facades\Redis::del($keys);
            }
        } catch (\Exception $e) {
            // Redis may not be available in all environments — non-fatal
            \Illuminate\Support\Facades\Log::warning('PermissionSeeder: could not bust role_perms cache', ['error' => $e->getMessage()]);
        }
    }
}
