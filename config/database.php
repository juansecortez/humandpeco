<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

'sqlsrv' => [
    'driver' => 'sqlsrv',
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', '1433'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,

    // Deben ser booleanos reales
     'encrypt' => env('DB_ORGANIGRAMA_ENCRYPT', 'no'),
    'trust_server_certificate' => filter_var(env('DB_TRUST_SERVER_CERTIFICATE', false), FILTER_VALIDATE_BOOLEAN),

    'options' => extension_loaded('sqlsrv') ? [
        'LoginTimeout' => (int) env('DB_LOGIN_TIMEOUT', 5),
        'ConnectRetryCount'    => (int) env('DB_CONNECT_RETRY_COUNT', 2),
        'ConnectRetryInterval' => (int) env('DB_CONNECT_RETRY_INTERVAL', 2),
        PDO::SQLSRV_ATTR_QUERY_TIMEOUT => (int) env('DB_QUERY_TIMEOUT', 120),
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    ] : [],
],
'organigrama' => [
        'driver' => 'sqlsrv',
        'url' => env('DATABASE_URL_ORGANIGRAMA'),
        'host' => env('DB_ORGANIGRAMA_HOST', '192.168.100.60'),
        'port' => env('DB_ORGANIGRAMA_PORT', '1433'),
        'database' => env('DB_ORGANIGRAMA_DATABASE', 'Organigrama'),
        'username' => env('DB_ORGANIGRAMA_USERNAME', 'tinformacion'),
        'password' => env('DB_ORGANIGRAMA_PASSWORD', 'Timeinlondon$'),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,

        // Claves importantes con ODBC 18+
        'encrypt' => env('DB_ORGANIGRAMA_ENCRYPT', 'yes'), // mantiene cifrado
        'trust_server_certificate' => env('DB_ORGANIGRAMA_TRUST_SERVER_CERTIFICATE', true),

        // Opcional: fuerza UTF-8 en el driver PDO SQLSRV
        'options' => extension_loaded('sqlsrv') ? [
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
        ] : [],
    ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'predis'),
        ],

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

    ],

];
