<?php

declare(strict_types=1);

namespace OCA\VocabularyTrainer\Controller;

use InvalidArgumentException;
use OCA\VocabularyTrainer\Service\CollectionService;
use OCA\VocabularyTrainer\Service\LearningService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ApiController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private CollectionService $collectionService,
        private LearningService $learningService,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function collectionsIndex(): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        return new DataResponse($this->collectionService->getCollections($userId));
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function dashboardStatistics(): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        return new DataResponse($this->collectionService->getDashboardStatistics($userId));
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function createCollection(): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        try {
            $this->collectionService->createCollection($userId, $this->getCollectionData());
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        return new DataResponse(['success' => true], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function updateCollection(int $id): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        try {
            $updated = $this->collectionService->updateCollection($userId, $id, $this->getCollectionData());
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        if (!$updated) {
            return $this->notFound('Collection not found.');
        }
        return new DataResponse(['success' => true]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function deleteCollection(int $id): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        if (!$this->collectionService->deleteCollection($userId, $id)) {
            return $this->notFound('Collection not found.');
        }
        return new DataResponse(['success' => true]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function entriesIndex(int $collectionId): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        $entries = $this->collectionService->getEntries($userId, $collectionId);
        if ($entries === null) {
            return $this->notFound('Collection not found.');
        }
        return new DataResponse($entries);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function collectionStatistics(int $collectionId): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        $statistics = $this->collectionService->getCollectionStatistics($userId, $collectionId);
        if ($statistics === null) {
            return $this->notFound('Collection not found.');
        }
        return new DataResponse($statistics);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function createEntry(int $collectionId): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        try {
            if (!$this->collectionService->createEntry($userId, $collectionId, $this->getEntryData())) {
                return $this->notFound('Collection not found.');
            }
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        return new DataResponse(['success' => true], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function updateEntry(int $collectionId, int $entryId): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        try {
            if (!$this->collectionService->updateEntry($userId, $collectionId, $entryId, $this->getEntryData())) {
                return $this->notFound('Entry not found.');
            }
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        return new DataResponse(['success' => true]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function deleteEntry(int $collectionId, int $entryId): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        try {
            if (!$this->collectionService->deleteEntry($userId, $collectionId, $entryId)) {
                return $this->notFound('Entry not found.');
            }
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        return new DataResponse(['success' => true]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function deleteEntries(int $collectionId): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        try {
            $deleted = $this->collectionService->deleteEntries($userId, $collectionId, $this->getEntryIds());
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        if ($deleted === null) {
            return $this->notFound('Collection not found.');
        }
        return new DataResponse(['deleted' => $deleted]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function moveEntries(int $collectionId): DataResponse {
        $userId = $this->getApiUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }
        $targetCollectionId = filter_var($this->request->getParam('targetCollectionId'), FILTER_VALIDATE_INT);
        if ($targetCollectionId === false || $targetCollectionId < 1) {
            return $this->badRequest(new InvalidArgumentException('Choose a valid destination collection.'));
        }
        try {
            $moved = $this->collectionService->moveEntries(
                $userId,
                $collectionId,
                $targetCollectionId,
                $this->getEntryIds()
            );
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        if ($moved === null) {
            return $this->notFound('Collection not found.');
        }
        return new DataResponse(['moved' => $moved]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function studySettings(int $collectionId): DataResponse {
        $userId = $this->getApiUserId();
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
    #[NoCSRFRequired]
    public function startStudySession(int $collectionId): DataResponse {
        $userId = $this->getApiUserId();
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
    #[NoCSRFRequired]
    public function submitAnswer(int $collectionId, int $entryId): DataResponse {
        $userId = $this->getApiUserId();
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

    private function getApiUserId(): ?string {
        $hasApiHeaders = strtolower($this->request->getHeader('ocs-apirequest')) === 'true'
            && $this->request->getHeader('x-vocabulary-trainer-api') === '1';
        if (!$hasApiHeaders) {
            return null;
        }
        return $this->userSession->getUser()?->getUID();
    }

    private function getCollectionData(): array {
        return [
            'name' => $this->request->getParam('name'),
            'sourceLanguage' => $this->request->getParam('sourceLanguage'),
            'targetLanguage' => $this->request->getParam('targetLanguage'),
            'boxCount' => $this->request->getParam('boxCount'),
        ];
    }

    private function getEntryData(): array {
        return [
            'sourceTerm' => $this->request->getParam('sourceTerm'),
            'targetTerm' => $this->request->getParam('targetTerm'),
            'notes' => $this->request->getParam('notes', ''),
        ];
    }

    private function getEntryIds(): array {
        $value = $this->request->getParam('entryIds');
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException('The selected entry IDs are invalid.');
        }
        return $value;
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
