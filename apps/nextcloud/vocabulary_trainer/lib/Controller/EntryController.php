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

class EntryController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private CollectionService $collectionService,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(int $collectionId): DataResponse {
        $userId = $this->getUserId();
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
    public function create(int $collectionId): DataResponse {
        $userId = $this->getUserId();
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
    public function update(int $collectionId, int $entryId): DataResponse {
        $userId = $this->getUserId();
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
    public function destroy(int $collectionId, int $entryId): DataResponse {
        $userId = $this->getUserId();
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
    public function bulkDestroy(int $collectionId): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        try {
            $deleted = $this->collectionService->deleteEntries(
                $userId,
                $collectionId,
                $this->getEntryIds()
            );
        } catch (InvalidArgumentException $exception) {
            return $this->badRequest($exception);
        }
        if ($deleted === null) {
            return $this->notFound('Collection not found.');
        }
        return new DataResponse(['deleted' => $deleted]);
    }

    #[NoAdminRequired]
    public function move(int $collectionId): DataResponse {
        $userId = $this->getUserId();
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

    private function getUserId(): ?string {
        return $this->userSession->getUser()?->getUID();
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
        if (!is_string($value)) {
            throw new InvalidArgumentException('Select one or more entries.');
        }
        $entryIds = json_decode($value, true);
        if (!is_array($entryIds) || array_is_list($entryIds) === false) {
            throw new InvalidArgumentException('The selected entry IDs are invalid.');
        }
        return $entryIds;
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
