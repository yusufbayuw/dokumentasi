<?php

namespace App\NetworkMonitoring;

use App\Models\NetworkSnmpProfile;
use RuntimeException;
use SNMP;

/**
 * Uses the native PHP SNMP extension, never invoking shell commands.
 * SNMPv3 authPriv remains the default. SNMPv2c is explicitly opt-in and
 * must only be used on tightly controlled management networks.
 * Credentials are decrypted in memory by encrypted Eloquent casts and are
 * never passed as command-line arguments or stored in measurement records.
 */
class NativeSnmpTransport
{
    public function available(): bool
    {
        return class_exists(SNMP::class);
    }

    /** @param list<string> $oids
     *  @return array<string, string>
     */
    public function read(NetworkSnmpProfile $profile, string $host, array $oids): array
    {
        if ($oids === []) {
            return [];
        }

        $session = $this->session($profile, $host);
        try {
            $response = @$session->get($oids, true);
            if (! is_array($response)) {
                throw new RuntimeException('snmp_read_failed');
            }

            $normalized = [];
            foreach ($response as $oid => $value) {
                $normalized[ltrim((string) $oid, '.')] = trim((string) $value, '" ');
            }

            return $normalized;
        } finally {
            $session->close();
        }
    }

    /** @return array<int, string> ifIndex => ifName */
    public function interfaceNames(NetworkSnmpProfile $profile, string $host): array
    {
        $session = $this->session($profile, $host);
        try {
            // IF-MIB::ifName. Only read from explicitly configured devices.
            $values = @$session->walk('.1.3.6.1.2.1.31.1.1.1.1', true, 16);
            if (! is_array($values)) {
                return [];
            }

            $names = [];
            foreach ($values as $suffix => $value) {
                $index = ltrim((string) $suffix, '.');
                if (ctype_digit($index) && (int) $index > 0 && (int) $index < 10000000) {
                    $names[(int) $index] = trim((string) $value, '" ');
                }
            }

            return $names;
        } finally {
            $session->close();
        }
    }

    private function session(NetworkSnmpProfile $profile, string $host): SNMP
    {
        if (! $this->available()) {
            throw new RuntimeException('snmp_extension_missing');
        }

        if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! app(TargetGuard::class)->allows('icmp', $host)) {
            throw new RuntimeException('snmp_host_not_allowed');
        }

        if (! SnmpProtocolPolicy::canPoll($profile)) {
            throw new RuntimeException('snmp_protocol_disabled_or_invalid');
        }

        $timeout = max(200000, min(2000000, (int) config('network_quality.snmp.timeout_us', 1000000)));

        if ($profile->version === '2c') {
            // PHP 8.1 native extension: SNMP::VERSION_2C (capital C).
            // Community never appears on a command line, URL, response or log.
            // v2c traffic is not encrypted; restrict devices to a management VLAN.
            $session = new SNMP(SNMP::VERSION_2C, $host, (string) $profile->community, $timeout, 0);
        } elseif ($profile->version === '3') {
            if (! in_array($profile->auth_protocol, ['SHA', 'SHA256', 'SHA512'], true)) {
                throw new RuntimeException('snmp_auth_protocol_not_allowed');
            }

            $session = new SNMP(SNMP::VERSION_3, $host, (string) $profile->security_name, $timeout, 0);

            try {
                if (! @$session->setSecurity(
                    'authPriv',
                    (string) $profile->auth_protocol,
                    (string) $profile->auth_passphrase,
                    'AES',
                    (string) $profile->privacy_passphrase
                )) {
                    throw new RuntimeException('snmp_security_setup_failed');
                }
            } catch (\Throwable $error) {
                $session->close();
                throw $error;
            }
        } else {
            throw new RuntimeException('snmp_version_not_supported');
        }

        try {
            $session->valueretrieval = SNMP_VALUE_PLAIN;
            $session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;

            return $session;
        } catch (\Throwable $error) {
            $session->close();
            throw $error;
        }
    }
}
