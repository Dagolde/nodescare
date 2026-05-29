<?php

namespace App\Console\Commands;

use App\Models\PatientRecord;
use App\Models\WorkflowTemplate;
use Illuminate\Console\Command;

class FixMissingWorkflowTemplates extends Command
{
    protected $signature = 'workflow:fix-missing-templates';
    protected $description = 'Fix patient records that are missing workflow template assignments';

    public function handle()
    {
        $this->info('🔍 Finding patient records without workflow templates...');
        
        $tenantId = '2266d360-5a0c-45fc-8b9e-d0e790b98026';
        
        // Find default workflow template
        $defaultTemplate = WorkflowTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'Standard Patient Journey')
            ->first();
            
        if (!$defaultTemplate) {
            $this->error('❌ Default workflow template "Standard Patient Journey" not found');
            return 1;
        }
        
        $this->info("✅ Found default template: {$defaultTemplate->name}");
        
        // Find patient records without workflow templates
        $recordsWithoutTemplate = PatientRecord::withoutGlobalScopes()
            ->with(['patient'])
            ->where('tenant_id', $tenantId)
            ->whereNull('workflow_template_id')
            ->get();
            
        if ($recordsWithoutTemplate->isEmpty()) {
            $this->info('✅ All patient records have workflow templates assigned');
            return 0;
        }
        
        $this->warn("🚨 Found {$recordsWithoutTemplate->count()} patient records without workflow templates:");
        
        foreach ($recordsWithoutTemplate as $record) {
            $this->line("  - {$record->patient->full_name} (#{$record->patient->patient_number})");
        }
        
        if (!$this->confirm('Do you want to assign the default workflow template to these records?')) {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        $this->info('🔧 Assigning default workflow template...');
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($recordsWithoutTemplate as $record) {
            try {
                $record->update([
                    'workflow_template_id' => $defaultTemplate->id,
                ]);
                
                $this->info("  ✅ Updated: {$record->patient->full_name}");
                $successCount++;
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed: {$record->patient->full_name} - {$e->getMessage()}");
                $failCount++;
            }
        }
        
        $this->info("\n📊 Results:");
        $this->info("  ✅ Successfully updated: {$successCount}");
        if ($failCount > 0) {
            $this->error("  ❌ Failed to update: {$failCount}");
        }
        
        return $failCount > 0 ? 1 : 0;
    }
}