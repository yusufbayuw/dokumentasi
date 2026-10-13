<?php

namespace App\Support;

/**
 * Separate queue/connection for fingerprint machine commands.
 * Ping and other application jobs continue using the default connection.
 */
final class FingerprintQueue
{
    public const CONNECTION = 'fingerprint';
    public const NAME = 'fingerprint';
}
