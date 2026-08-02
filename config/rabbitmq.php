<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Broker Connection (topology setup)
    |--------------------------------------------------------------------------
    |
    | Used by rabbitmq:setup-topology to talk to the broker. Domain job
    | publishing uses rabbitmq-invoices / rabbitmq-email instead.
    |
    */

    'connection' => env('RABBITMQ_QUEUE_CONNECTION', 'rabbitmq'),

    'failed_routing_key' => env('RABBITMQ_FAILED_ROUTING_KEY', '%s.failed'),

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    |
    | Each domain has its own Laravel queue connection, jobs exchange (direct),
    | and DLX. The invoices domain also reserves invoices.events (topic) for
    | future domain-event pub/sub — declared by topology, no bindings yet.
    |
    */

    'domains' => [

        'invoices' => [
            'connection' => 'rabbitmq-invoices',
            'jobs_exchange' => env('RABBITMQ_INVOICES_JOBS_EXCHANGE', 'invoices.jobs'),
            'jobs_exchange_type' => 'direct',
            'events_exchange' => env('RABBITMQ_INVOICES_EVENTS_EXCHANGE', 'invoices.events'),
            'events_exchange_type' => 'topic',
            'dlx' => env('RABBITMQ_INVOICES_DLX', 'invoices.dlx'),
            'queues' => [
                'pdf',
            ],
        ],

        'email' => [
            'connection' => 'rabbitmq-email',
            'jobs_exchange' => env('RABBITMQ_EMAIL_JOBS_EXCHANGE', 'email.jobs'),
            'jobs_exchange_type' => 'direct',
            'dlx' => env('RABBITMQ_EMAIL_DLX', 'email.dlx'),
            'queues' => [
                'email',
            ],
        ],

    ],

];
