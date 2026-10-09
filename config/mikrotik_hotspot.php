<?php

return [
    // This integration is independent of the WPA3-Enterprise / FreeRADIUS NAC.
    'enabled' => (bool) env('MIKROTIK_HOTSPOT_ENABLED', false),
    'url' => env('MIKROTIK_HOTSPOT_URL', ''),
    'username' => env('MIKROTIK_HOTSPOT_USERNAME', ''),
    'password' => env('MIKROTIK_HOTSPOT_PASSWORD', ''),
    'user_secret' => env('MIKROTIK_HOTSPOT_USER_SECRET', ''),
    'profile' => env('MIKROTIK_HOTSPOT_PROFILE', 'default'),
    'ca_bundle' => env('MIKROTIK_HOTSPOT_CA_BUNDLE', ''),
];
