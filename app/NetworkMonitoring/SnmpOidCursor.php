<?php

namespace App\NetworkMonitoring;

class SnmpOidCursor
{
    public static function normalize(string $oid): ?string
    {
        $oid = ltrim(trim($oid), '.');
        if (strlen($oid) > 768 || ! preg_match('/^\d+(?:\.\d+)+$/D', $oid)) {
            return null;
        }

        $numbers = explode('.', $oid);
        if (count($numbers) > 128) {
            return null;
        }

        $canonical = [];
        foreach ($numbers as $number) {
            $value = ltrim($number, '0');
            $value = $value === '' ? '0' : $value;
            if (strlen($value) > 10 || (int) $value > 4294967295) {
                return null;
            }
            $canonical[] = $value;
        }

        return implode('.', $canonical);
    }

    public static function within(string $oid, string $root): bool
    {
        return $oid === $root || str_starts_with($oid, $root.'.');
    }

    public static function greaterThan(string $oid, string $previous): bool
    {
        $a = explode('.', $oid);
        $b = explode('.', $previous);
        $limit = min(count($a), count($b));

        for ($i = 0; $i < $limit; $i++) {
            if ((int) $a[$i] !== (int) $b[$i]) {
                return (int) $a[$i] > (int) $b[$i];
            }
        }

        return count($a) > count($b);
    }

    /** @return list<string> */
    public static function validRoots(array $roots): array
    {
        $result = [];
        foreach ($roots as $candidate) {
            $root = self::normalize((string) $candidate);
            if ($root === null || ! self::within($root, '1.3.6.1')) {
                continue;
            }

            if (! in_array($root, $result, true)) {
                $result[] = $root;
            }

            if (count($result) >= 10) {
                break;
            }
        }

        return $result;
    }
}
