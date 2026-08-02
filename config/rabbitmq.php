<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    |
    | Topology setup uses this Laravel queue connection name.
    |
    */

    'connection' => env('RABBITMQ_QUEUE_CONNECTION', 'rabbitmq'),

    /*
    |--------------------------------------------------------------------------
    | Dead Letter Exchange
    |--------------------------------------------------------------------------
    |
    | After a job exhausts its retries, the RabbitMQ driver rejects the message
    | (requeue=false). Queues declared with x-dead-letter-* route those messages
    | here. Intermediate retries use per-delay TTL queues (also DLX-based).
    |
    */

    'dlx' => [
        'exchange' => env('RABBITMQ_DLX_EXCHANGE', 'invoices.dlx'),
        'type' => env('RABBITMQ_DLX_TYPE', 'direct'),
    ],

    'failed_routing_key' => env('RABBITMQ_FAILED_ROUTING_KEY', '%s.failed'),

    /*
    |--------------------------------------------------------------------------
    | Application Queues
    |--------------------------------------------------------------------------
    |
    | Work queues declared by `rabbitmq:setup-topology` with DLX arguments.
    | Keep this list in sync with job onQueue() names.
    |
    */

    'queues' => [
        'default',
        'pdf',
        'email',
    ],

];
