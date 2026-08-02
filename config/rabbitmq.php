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
    | and DLX. The invoices domain publishes lifecycle events to invoices.events
    | (topic); event_queues are bound with topic routing keys.
    |
    | Note: Laravel Queue is a work-queue abstraction (serialized PHP jobs), not a
    | full AMQP event bus. Topic fan-out is real in RabbitMQ; consumers still share
    | one job class and branch on the consumed queue (see README).
    |
    */

    'domains' => [

        'invoices' => [
            'connection' => 'rabbitmq-invoices',
            'jobs_exchange' => env('RABBITMQ_INVOICES_JOBS_EXCHANGE', 'invoices.jobs'),
            'jobs_exchange_type' => 'direct',
            'events_exchange' => env('RABBITMQ_INVOICES_EVENTS_EXCHANGE', 'invoices.events'),
            'events_exchange_type' => 'topic',
            'events_connection' => 'rabbitmq-invoices-events',
            'dlx' => env('RABBITMQ_INVOICES_DLX', 'invoices.dlx'),
            'queues' => [
                'pdf',
            ],
            'event_queues' => [
                [
                    'name' => 'invoice.metrics',
                    'binding_keys' => ['invoice.#'],
                ],
                [
                    'name' => 'invoice.audit',
                    'binding_keys' => ['invoice.#'],
                ],
                [
                    'name' => 'invoice.webhooks',
                    'binding_keys' => ['invoice.paid'],
                ],
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
