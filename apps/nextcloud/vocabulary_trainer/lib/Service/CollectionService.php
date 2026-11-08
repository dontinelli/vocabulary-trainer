<?php

namespace OCA\VocabularyTrainer\Service;

class CollectionService {
    public function __construct() {
    }

    public function getCollectionSummary(): array {
        return [
            'collections' => 0,
            'entries' => 0,
            'status' => 'not_initialized',
        ];
    }
}
