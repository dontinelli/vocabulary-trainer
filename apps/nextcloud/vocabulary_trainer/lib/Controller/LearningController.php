<?php

declare(strict_types=1);

namespace OCA\VocabularyTrainer\Controller;

use InvalidArgumentException;
use OCA\VocabularyTrainer\Service\LearningService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class LearningController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private LearningService $learningService,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function settings(int $collectionId): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        $settings = $this->learningService->getSettings($userId, $collectionId);
        if ($settings === null) {
            return $this->notFound('Collection not found.');
        }
        return new DataResponse($settings);
    }

    #[NoAdminRequired]
    public function start(int $collectionId): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        try {
            $session = $this->learningService->startSession($userId, $collectionId, [
                'direction' => $this->request->getParam('direction'),
                'mode' => $this->request->getParam('mode'),
                'selection' => $this->request->getParam('selection'),
                'box' => $this->request->getParam('box'),
                'errorRate' => $this->request->getParam('errorRate'),
                'days' => $this->request->getParam('days'),
                'startWithNew' => $this->request->getParam('startWithNew'),
                'includeFinalBox' => $this->request->getParam('includeFinalBox'),
                'answerDelaySeconds' => $this->request->getParam('answerDelaySeconds'),
                'questionTimeLimitSeconds' => $this->request->getParam('questionTimeLimitSeconds'),
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        if ($session === null) {
            return $this->notFound('Collection not found.');
        }
        return new DataResponse($session, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function answer(int $collectionId, int $entryId): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        try {
            $result = $this->learningService->answer($userId, $collectionId, $entryId, [
                'direction' => $this->request->getParam('direction'),
                'answer' => $this->request->getParam('answer'),
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        if ($result === null) {
            return $this->notFound('Collection or word pair not found.');
        }
        return new DataResponse($result);
    }

    private function getUserId(): ?string {
        return $this->userSession->getUser()?->getUID();
    }

    private function unauthorized(): DataResponse {
        return new DataResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
    }

    private function notFound(string $message): DataResponse {
        return new DataResponse(['error' => $message], Http::STATUS_NOT_FOUND);
    }

    private function badRequest(InvalidArgumentException $exception): DataResponse {
        return new DataResponse(['error' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
    }
}
