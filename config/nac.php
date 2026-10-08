<?php

return [
    // Provide a strong random value outside source control.
    'radius_api_key' => env('NAC_RADIUS_API_KEY'),
    // Comma-separated IPs of trusted RADIUS hosts. Empty means no IP filter.
    'radius_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('NAC_RADIUS_ALLOWED_IPS', ''))
    ))),
    'auth_history_retention_days' => (int) env('NAC_AUTH_HISTORY_RETENTION_DAYS', 90),
];
