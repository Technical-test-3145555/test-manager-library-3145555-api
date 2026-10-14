<?php

return [
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_ttl' => 8 * 3600,

    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),

    'cors_origin' => env('CORS_ORIGIN'),
    'covers_dir' => env('COVERS_DIR', storage_path('app/covers')),

    // DT-21: optional shift of the clock, only to seed overdue demo loans. Forbidden in production.
    'clock_offset_days' => (int) env('CLOCK_OFFSET_DAYS', 0),
];
