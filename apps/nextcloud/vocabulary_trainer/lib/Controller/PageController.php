<?php

namespace OCA\VocabularyTrainer\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
    public function __construct(string $appName, IRequest $request) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        Util::addScript($this->appName, 'app');
        Util::addStyle($this->appName, 'app');

        return new TemplateResponse($this->appName, 'main');
    }

    #[NoCSRFRequired]
    public function status(): DataResponse {
        return new DataResponse([
            'app' => 'vocabulary_trainer',
            'status' => 'up',
        ]);
    }
}
