<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SQL Server Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to your standalone SQL Server 2012 database
    | for data migration purposes.
    |
    */

    'default' => env('SQLSERVER_CONNECTION', 'sqlserver'),

    'connections' => [
        'sqlserver' => [
            'driver' => 'sqlsrv',
            'host' => env('SQLSERVER_HOST', 'localhost'),
            'port' => env('SQLSERVER_PORT', '1433'),
            'database' => env('SQLSERVER_DATABASE', ''),
            'username' => env('SQLSERVER_USERNAME', ''),
            'password' => env('SQLSERVER_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'options' => [
                // SQL Server specific options
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        ],
    ],
];
