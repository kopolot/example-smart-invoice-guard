<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Connection
    |--------------------------------------------------------------------------
    |
    | Local Docker Compose exposes the node as hostname `elasticsearch`.
    | Disable the integration entirely with ELASTICSEARCH_ENABLED=false.
    |
    */

    'enabled' => (bool) env('ELASTICSEARCH_ENABLED', true),

    'host' => env('ELASTICSEARCH_HOST', 'http://elasticsearch:9200'),

    'invoices_index' => env('ELASTICSEARCH_INVOICES_INDEX', 'invoices'),

];
