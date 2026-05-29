<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClearPermissionCache extends Command
{
    protected $signature = 'permissions:clear-cache';
    protected $description = 'Clear the Redis role_perms cache so updated permissions take effect immediately';

    public function handle(): int
    {
        try {
            $redis = \Illuminate\Support\Facades\Redis::connection();
            $keys = $redis->keys('*:role_perms:*');

            if (empty($keys)) {
                $this->info('No role_perms cache keys found — nothing to clear.');
                return self::SUCCESS;
            }

            $redis->del($keys);
            $this->info('Cleared ' . count($keys) . ' role_perms cache key(s).');
        } catch (\Exception $e) {
            $this->error('Failed to clear cache: ' . $e->getMessage());
            Log::error('permissions:clear-cache failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
