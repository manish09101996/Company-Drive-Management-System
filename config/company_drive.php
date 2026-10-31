<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hostinger Plan Storage Quota (GB)
    |--------------------------------------------------------------------------
    |
    | Hostinger "Unlimited Web Hosting" plan includes 50 GB NVMe Storage.
    |
    */
    'storage_quota_gb' => (float) env('STORAGE_QUOTA_GB', 50),

    /*
    |--------------------------------------------------------------------------
    | Hostinger Resource Limits
    |--------------------------------------------------------------------------
    */
    'plan_name' => 'Hostinger Unlimited Web Hosting (50 GB NVMe)',
    'inodes_limit' => (int) env('HOSTINGER_INODES_LIMIT', 600000),
    'php_workers' => (int) env('HOSTINGER_PHP_WORKERS', 60),
    'mysql_connections' => (int) env('HOSTINGER_MYSQL_CONNECTIONS', 75),

    /*
    |--------------------------------------------------------------------------
    | Ownership Keyword Mapping for Takeout Imports
    |--------------------------------------------------------------------------
    */
    'ownership_mapping' => [
        'garima@arklehomes.com.au' => ['garima'],
        'ishan@arklehomes.com.au'  => ['ishan'],
        'sean@arklehomes.com.au'   => ['sean'],
        'skmann@arklehomes.com.au' => ['sk mann', 'skmann'],
    ],
];
