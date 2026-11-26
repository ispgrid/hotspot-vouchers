<?php

/*
 * The vouchers database. An empty value takes the app's own MySQL connection setting; the database name defaults
 * to ispgrid_vouchers. Read here, not in the service provider, so the values survive `config:cache`.
 */
return [
    'database' => [
        'host' => env('VOUCHERS_DB_HOST'),
        'port' => env('VOUCHERS_DB_PORT'),
        'database' => env('VOUCHERS_DB_DATABASE'),
        'username' => env('VOUCHERS_DB_USERNAME'),
        'password' => env('VOUCHERS_DB_PASSWORD'),
    ],
];
