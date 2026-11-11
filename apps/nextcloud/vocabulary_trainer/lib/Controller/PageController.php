<?php

namespace OCA\VocabularyTrainer\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class PageController extends Controller {
    public function __construct(string $appName, IRequest $request) {
        parent::__construct($appName, $request);
    }

    public function index(): DataResponse {
        return new DataResponse([
            'app' => 'vocabulary_trainer',
            'status' => 'ok',
            'message' => 'Vocabulary Trainer app loaded.',
        ]);
    }

    public function status(): DataResponse {
        return new DataResponse([
            'app' => 'vocabulary_trainer',
            'status' => 'up',
        ]);
    }
}
