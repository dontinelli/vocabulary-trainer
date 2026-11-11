<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#status', 'url' => '/status', 'verb' => 'GET'],
        ['name' => 'collection#index', 'url' => '/collections', 'verb' => 'GET'],
        ['name' => 'collection#create', 'url' => '/collections', 'verb' => 'POST'],
        ['name' => 'collection#update', 'url' => '/collections/{id}', 'verb' => 'PUT'],
        ['name' => 'collection#destroy', 'url' => '/collections/{id}', 'verb' => 'DELETE'],
    ],
];
