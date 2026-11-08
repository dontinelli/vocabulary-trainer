<?php

namespace OCA\VocabularyTrainer\AppInfo;

use OCP\AppFramework\App;

class Application extends App {
    public const APP_ID = 'vocabulary_trainer';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }
}
