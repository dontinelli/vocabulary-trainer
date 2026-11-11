<?php

declare(strict_types=1);

namespace OCA\VocabularyTrainer\Controller;

use InvalidArgumentException;
use OCA\VocabularyTrainer\Service\CollectionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class CollectionController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private CollectionService $collectionService,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        return new DataResponse($this->collectionService->getCollections($userId));
    }

    #[NoAdminRequired]
    public function create(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->collectionService->createCollection($userId, $this->getCollectionData());
        } catch (InvalidArgumentException $exception) {
            return new DataResponse(['error' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new DataResponse(['success' => true], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $updated = $this->collectionService->updateCollection($userId, $id, $this->getCollectionData());
        } catch (InvalidArgumentException $exception) {
            return new DataResponse(['error' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        if (!$updated) {
            return new DataResponse(['error' => 'Collection not found.'], Http::STATUS_NOT_FOUND);
        }

        return new DataResponse(['success' => true]);
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        if (!$this->collectionService->deleteCollection($userId, $id)) {
            return new DataResponse(['error' => 'Collection not found.'], Http::STATUS_NOT_FOUND);
        }

        return new DataResponse(['success' => true]);
    }

    private function getUserId(): ?string {
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
}
