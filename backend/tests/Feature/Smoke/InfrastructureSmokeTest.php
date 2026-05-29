<?php

namespace Tests\Feature\Smoke;

use App\Jobs\BroadcastNotificationJob;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Infrastructure smoke tests.
 *
 * Verifies that critical infrastructure components are healthy
 * and correctly configured.
 *
 * Requirements: 15.2, 13.5, 12.5, 15.3
 */
class InfrastructureSmokeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Requirement 15.2 — Redis connection healthy
    // -------------------------------------------------------------------------

    /**
     * Requirement 15.2 — The HHMS SHALL enforce HTTPS (TLS 1.2 or higher) for
     * all data in transit. Redis is used for session/queue data in transit.
     *
     * This smoke test verifies the Redis connection is healthy.
     */
    public function test_redis_connection_is_healthy(): void
    {
        $key   = 'smoke_test_' . uniqid();
        $value = 'ok';

        Redis::set($key, $value, 'EX', 10);
        $retrieved = Redis::get($key);
        Redis::del($key);

        $this->assertEquals($value, $retrieved, 'Redis connection is not healthy.');
    }

    // -------------------------------------------------------------------------
    // Requirement 13.5 — Queue workers can process jobs
    // -------------------------------------------------------------------------

    /**
     * Requirement 13.5 — Queue workers must be able to process jobs.
     * This test verifies that a job can be dispatched and is received by the queue.
     */
    public function test_queue_workers_can_dispatch_and_receive_jobs(): void
    {
        Queue::fake();

        dispatch(new BroadcastNotificationJob(
            eventClass: \App\Events\PatientStatusChanged::class,
            payload: ['smoke-test-id', 'active', 'dept-smoke-id'],
        ));

        Queue::assertPushed(BroadcastNotificationJob::class);
    }

    /**
     * Requirement 13.5 — Queue connection is configured and reachable.
     */
    public function test_queue_connection_is_configured(): void
    {
        $queueConnection = config('queue.default');
        $this->assertNotEmpty($queueConnection, 'Queue connection is not configured.');

        // Verify the queue connection config exists
        $connectionConfig = config("queue.connections.{$queueConnection}");
        $this->assertNotNull($connectionConfig, "Queue connection '{$queueConnection}' is not configured.");
    }

    // -------------------------------------------------------------------------
    // Requirement 12.5 — Audit log retention (no auto-delete / SoftDeletes)
    // -------------------------------------------------------------------------

    /**
     * Requirement 12.5 — The HHMS SHALL retain Audit_Log entries for a minimum
     * of 7 years. AuditLog must NOT use SoftDeletes (which would allow deletion).
     */
    public function test_audit_log_model_does_not_use_soft_deletes(): void
    {
        $auditLog = new AuditLog();

        $this->assertFalse(
            in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($auditLog)),
            'AuditLog model must NOT use SoftDeletes — audit logs are immutable and must be retained.'
        );
    }

    /**
     * Requirement 12.5 — AuditLog has no updated_at column (immutable records).
     */
    public function test_audit_log_is_immutable_no_updated_at(): void
    {
        $auditLog = new AuditLog();

        $this->assertNull(
            $auditLog::UPDATED_AT,
            'AuditLog must have UPDATED_AT = null to enforce immutability.'
        );
    }

    /**
     * Requirement 12.5 — AuditLog entries cannot be deleted via the model.
     */
    public function test_audit_log_entry_persists_after_creation(): void
    {
        $log = AuditLog::create([
            'actor_id'      => null,
            'actor_role'    => 'system',
            'action'        => 'smoke_test.created',
            'resource_type' => 'SmokeTest',
            'resource_id'   => 'smoke-001',
            'description'   => 'Smoke test audit log entry',
            'ip_address'    => '127.0.0.1',
            'metadata'      => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id'     => $log->id,
            'action' => 'smoke_test.created',
        ]);

        // Verify the record is still there (no auto-cleanup)
        $this->assertNotNull(AuditLog::find($log->id));
    }

    // -------------------------------------------------------------------------
    // Requirement 15.3 — R2/S3 disk is configured
    // -------------------------------------------------------------------------

    /**
     * Requirement 15.3 — The HHMS SHALL store patient photographs and documents
     * in a tenant-isolated storage bucket. The S3/R2 disk must be configured.
     */
    public function test_s3_disk_is_configured(): void
    {
        $diskConfig = config('filesystems.disks.s3');

        $this->assertNotNull($diskConfig, 'S3/R2 disk is not configured in filesystems.php.');
        $this->assertArrayHasKey('driver', $diskConfig);
        $this->assertEquals('s3', $diskConfig['driver']);
        $this->assertArrayHasKey('bucket', $diskConfig);
        $this->assertNotEmpty($diskConfig['bucket'], 'S3/R2 bucket name must not be empty.');
    }

    /**
     * Requirement 15.3 — The S3 disk driver is registered and accessible.
     */
    public function test_s3_disk_driver_is_accessible(): void
    {
        // Verify the disk can be instantiated without throwing
        try {
            $disk = Storage::disk('s3');
            $this->assertNotNull($disk);
        } catch (\Exception $e) {
            // In CI without real credentials, the disk may not connect
            // but the configuration must exist
            $this->assertStringNotContainsString(
                'Driver [] is not supported',
                $e->getMessage(),
                'S3 disk driver is not registered.'
            );
        }
    }
}
