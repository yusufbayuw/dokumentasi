<?php

return [
    'monitoring' => [
        'stale_minutes' => (int) env('CMDB_MONITORING_STALE_MINUTES', 15),
        'offline_after_failures' => (int) env('CMDB_OFFLINE_AFTER_FAILURES', 3),
        'schedule_minutes' => (int) env('CMDB_MONITORING_SCHEDULE_MINUTES', 5),
    ],
];
