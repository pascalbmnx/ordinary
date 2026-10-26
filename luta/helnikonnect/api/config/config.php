<?php
/**
 * Application configuration.
 */
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'helpkonnect',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    'storage' => [
        'path' => dirname(__DIR__, 2) . '/storage',
        'documents' => '/documents',
        'applications' => '/applications',
        'drawings' => '/drawings',
        'profiles' => '/profiles',
    ],

    // Root of the API (used to build absolute URLs)
    'base_url' => '/helpkonnect/api',

    'uploads' => [
        'max_size' => 10 * 1024 * 1024, // 10 MB default, overridden by settings
        'allowed_extensions' => [
            'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
            'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'dwg', 'dxf',
        ],
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'text/plain',
            'application/octet-stream', // for dwg/dxf
        ],
    ],

    'security' => [
        'login_max_attempts' => 5,
        'token_ttl' => 3600 * 12, // 12 hours for API session token
        'password_reset_ttl' => 3600 * 2, // 2 hours
    ],
];
