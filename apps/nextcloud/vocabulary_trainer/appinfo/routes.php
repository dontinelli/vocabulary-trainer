<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#status', 'url' => '/status', 'verb' => 'GET'],
        ['name' => 'collection#index', 'url' => '/collections', 'verb' => 'GET'],
        ['name' => 'collection#create', 'url' => '/collections', 'verb' => 'POST'],
        ['name' => 'collection#update', 'url' => '/collections/{id}', 'verb' => 'PUT'],
        ['name' => 'collection#destroy', 'url' => '/collections/{id}', 'verb' => 'DELETE'],
        ['name' => 'entry#index', 'url' => '/collections/{collectionId}/entries', 'verb' => 'GET'],
        ['name' => 'entry#statistics', 'url' => '/collections/{collectionId}/statistics', 'verb' => 'GET'],
        ['name' => 'entry#create', 'url' => '/collections/{collectionId}/entries', 'verb' => 'POST'],
        ['name' => 'entry#move', 'url' => '/collections/{collectionId}/entries/move', 'verb' => 'POST'],
        ['name' => 'entry#bulkDestroy', 'url' => '/collections/{collectionId}/entries', 'verb' => 'DELETE'],
        ['name' => 'entry#update', 'url' => '/collections/{collectionId}/entries/{entryId}', 'verb' => 'PUT'],
        ['name' => 'entry#destroy', 'url' => '/collections/{collectionId}/entries/{entryId}', 'verb' => 'DELETE'],
        ['name' => 'learning#settings', 'url' => '/collections/{collectionId}/study/settings', 'verb' => 'GET'],
        ['name' => 'learning#start', 'url' => '/collections/{collectionId}/study/session', 'verb' => 'POST'],
        ['name' => 'learning#answer', 'url' => '/collections/{collectionId}/study/entries/{entryId}/answer', 'verb' => 'POST'],
    ],
];
