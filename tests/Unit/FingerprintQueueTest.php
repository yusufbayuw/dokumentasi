<?php

namespace Tests\Unit;

use App\Jobs\RestartFingerprintDevice;
use App\Jobs\SyncFingerprintDevice;
use App\Support\FingerprintQueue;
use PHPUnit\Framework\TestCase;

class FingerprintQueueTest extends TestCase
{
    public function test_all_fingerprint_jobs_use_dedicated_queue_and_connection(): void
    {
        $restart = new RestartFingerprintDevice(361, 1, 'bulk');
        $sync = new SyncFingerprintDevice(361, 1, 'information');

        $this->assertSame('fingerprint', FingerprintQueue::NAME);
        $this->assertSame('fingerprint', FingerprintQueue::CONNECTION);
        $this->assertSame(FingerprintQueue::NAME, $restart->queue);
        $this->assertSame(FingerprintQueue::NAME, $sync->queue);
        $this->assertSame(FingerprintQueue::CONNECTION, $restart->connection);
        $this->assertSame(FingerprintQueue::CONNECTION, $sync->connection);
    }
}
