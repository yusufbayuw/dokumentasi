<?php

namespace Tests\Feature;

use App\Support\FingerprintQueue;
use Tests\TestCase;

class FingerprintQueueConfigurationTest extends TestCase
{
    public function test_fingerprint_connection_is_separate_from_monitoring_defaults(): void
    {
        $fingerprint = config('queue.connections.'.FingerprintQueue::CONNECTION);
        $database = config('queue.connections.database');

        $this->assertSame('database', $fingerprint['driver']);
        $this->assertSame('jobs', $fingerprint['table']);
        $this->assertSame(FingerprintQueue::NAME, $fingerprint['queue']);
        $this->assertGreaterThan(200, $fingerprint['retry_after']);

        // The generic queue retains its own timeout and queue.
        $this->assertSame('default', $database['queue']);
        $this->assertSame(90, $database['retry_after']);
    }
}
