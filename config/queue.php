<?php

return [
    // Las claves y nombres de controladores son contratos de Laravel.
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'connection' => env('DB_QUEUE_CONNECTION'), 'table' => env('DB_QUEUE_TABLE', 'mensajero_trabajos'), 'queue' => env('DB_QUEUE', 'default'), 'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90), 'after_commit' => true],
        'beanstalkd' => ['driver' => 'beanstalkd', 'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'), 'queue' => env('BEANSTALKD_QUEUE', 'default'), 'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90), 'block_for' => 0, 'after_commit' => true],
        'sqs' => ['driver' => 'sqs', 'key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'), 'queue' => env('SQS_QUEUE', 'default'), 'suffix' => env('SQS_SUFFIX'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1'), 'after_commit' => true],
        'redis' => ['driver' => 'redis', 'connection' => env('REDIS_QUEUE_CONNECTION', 'default'), 'queue' => env('REDIS_QUEUE', 'mensajero'), 'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 180), 'block_for' => null, 'after_commit' => true],
        'deferred' => ['driver' => 'deferred'],
        'background' => ['driver' => 'background'],
        'failover' => ['driver' => 'failover', 'connections' => ['redis', 'database']],
    ],
    'batching' => ['database' => env('DB_CONNECTION', 'sqlsrv'), 'table' => 'mensajero_lotes_trabajos'],
    'failed' => ['driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'), 'database' => env('DB_CONNECTION', 'sqlsrv'), 'table' => 'mensajero_trabajos_fallidos'],
];
