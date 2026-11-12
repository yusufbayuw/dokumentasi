<?php

namespace App\NetworkMonitoring;

use App\Models\NetworkSnmpProfile;

class SnmpProtocolPolicy
{
    public static function canPoll(?NetworkSnmpProfile $profile): bool
    {
        if (! $profile?->enabled) {
            return false;
        }

        if ($profile->version === '3') {
            // Preserve the existing v3 authPriv collector. Credentials are
            // verified by the native transport (encrypted at rest).
            return true;
        }

        return $profile->version === '2c'
            && (bool) config('network_quality.snmp.v2c_enabled', false)
            && self::communityIsSafe($profile->community);
    }

    public static function communityIsSafe(?string $community): bool
    {
        if ($community === null || strlen($community) < 8 || strlen($community) > 128) {
            return false;
        }

        if (trim($community) !== $community
            || preg_match('/[\x00-\x1f\x7f]/', $community)) {
            return false;
        }

        // Discourage shared, well-known default communities.
        return ! in_array(strtolower($community), ['public', 'private'], true);
    }
}
