<?php

declare(strict_types=1);

namespace OCA\VocabularyTrainer\Service;

use InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CollectionService {
    public function __construct(
        private IDBConnection $db,
    ) {
    }

    public function getCollections(string $userId): array {
        $query = $this->db->getQueryBuilder();
        $rows = $query
            ->select(
                'c.id AS id',
                'c.name AS name',
                'c.source_language AS source_language',
                'c.target_language AS target_language',
                'c.box_count AS box_count'
            )
            ->from('vocabulary_collections', 'c')
            ->where($query->expr()->eq('c.user_id', $query->createNamedParameter($userId)))
            ->orderBy('c.updated_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        if ($rows === []) {
            return [];
        }

        $collectionIds = array_map('intval', array_column($rows, 'id'));
        $boxCountsByCollection = [];
        foreach ($rows as $row) {
            $boxCountsByCollection[(int)$row['id']] = (int)$row['box_count'];
        }
        $entryQuery = $this->db->getQueryBuilder();
        $entryRows = $entryQuery
            ->select('collection_id')
            ->from('vocabulary_entries')
            ->where($entryQuery->expr()->in(
                'collection_id',
                $entryQuery->createNamedParameter($collectionIds, IQueryBuilder::PARAM_INT_ARRAY)
            ))
            ->executeQuery()
            ->fetchAllAssociative();

        $entryCounts = [];
        foreach ($entryRows as $entryRow) {
            $collectionId = (int)$entryRow['collection_id'];
            $entryCounts[$collectionId] = ($entryCounts[$collectionId] ?? 0) + 1;
        }

        $progressQuery = $this->db->getQueryBuilder();
        $progressRows = $progressQuery
            ->select('e.collection_id AS collection_id', 'p.box AS box')
            ->from('vocabulary_progress', 'p')
            ->leftJoin('p', 'vocabulary_entries', 'e', 'e.id = p.entry_id')
            ->where($progressQuery->expr()->eq('p.user_id', $progressQuery->createNamedParameter($userId)))
            ->andWhere($progressQuery->expr()->in(
                'e.collection_id',
                $progressQuery->createNamedParameter($collectionIds, IQueryBuilder::PARAM_INT_ARRAY)
            ))
            ->executeQuery()
            ->fetchAllAssociative();

        $progressScores = [];
        foreach ($progressRows as $progressRow) {
            $collectionId = (int)$progressRow['collection_id'];
            $boxCount = $boxCountsByCollection[$collectionId];
            $box = min($boxCount, max(1, (int)$progressRow['box']));
            $progressScores[$collectionId] = ($progressScores[$collectionId] ?? 0)
                + (($box - 1) / max(1, $boxCount - 1));
        }

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'sourceLanguage' => $row['source_language'],
            'targetLanguage' => $row['target_language'],
            'boxCount' => (int)$row['box_count'],
            'entryCount' => $entryCounts[(int)$row['id']] ?? 0,
            'learningProgressPercent' => ($entryCounts[(int)$row['id']] ?? 0) > 0
                ? (($progressScores[(int)$row['id']] ?? 0) / (($entryCounts[(int)$row['id']] ?? 0) * 2)) * 100
                : null,
        ], $rows);
    }

    public function createCollection(string $userId, array $data): void {
        $collection = $this->validateCollection($data);
        $now = gmdate('Y-m-d H:i:s');

        $query = $this->db->getQueryBuilder();
        $query->insert('vocabulary_collections')
            ->values([
                'user_id' => $query->createNamedParameter($userId),
                'name' => $query->createNamedParameter($collection['name']),
                'source_language' => $query->createNamedParameter($collection['sourceLanguage']),
                'target_language' => $query->createNamedParameter($collection['targetLanguage']),
                'box_count' => $query->createNamedParameter($collection['boxCount']),
                'created_at' => $query->createNamedParameter($now),
                'updated_at' => $query->createNamedParameter($now),
            ])
            ->executeStatement();
    }

    public function updateCollection(string $userId, int $id, array $data): bool {
        $collection = $this->validateCollection($data);
        $query = $this->db->getQueryBuilder();
        $affectedRows = $query->update('vocabulary_collections')
            ->set('name', $query->createNamedParameter($collection['name']))
            ->set('source_language', $query->createNamedParameter($collection['sourceLanguage']))
            ->set('target_language', $query->createNamedParameter($collection['targetLanguage']))
            ->set('box_count', $query->createNamedParameter($collection['boxCount']))
            ->set('updated_at', $query->createNamedParameter(gmdate('Y-m-d H:i:s')))
            ->where($query->expr()->eq('id', $query->createNamedParameter($id)))
            ->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->executeStatement();
        if ($affectedRows > 0) {
            return true;
        }

        $check = $this->db->getQueryBuilder();
        return $check->select('id')
            ->from('vocabulary_collections')
            ->where($check->expr()->eq('id', $check->createNamedParameter($id)))
            ->andWhere($check->expr()->eq('user_id', $check->createNamedParameter($userId)))
            ->executeQuery()
            ->fetchOne() !== false;
    }

    public function deleteCollection(string $userId, int $id): bool {
        $query = $this->db->getQueryBuilder();
        $affectedRows = $query->delete('vocabulary_collections')
            ->where($query->expr()->eq('id', $query->createNamedParameter($id)))
            ->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->executeStatement();

        return $affectedRows > 0;
    }

    public function getEntries(string $userId, int $collectionId): ?array {
        if (!$this->ownsCollection($userId, $collectionId)) {
            return null;
        }

        $query = $this->db->getQueryBuilder();
        $rows = $query
            ->select('id', 'source_term', 'target_term', 'notes')
            ->from('vocabulary_entries')
            ->where($query->expr()->eq('collection_id', $query->createNamedParameter($collectionId)))
            ->orderBy('source_term', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'sourceTerm' => $row['source_term'],
            'targetTerm' => $row['target_term'],
            'notes' => $row['notes'],
        ], $rows);
    }

    public function getCollectionStatistics(string $userId, int $collectionId): ?array {
        $collectionQuery = $this->db->getQueryBuilder();
        $collection = $collectionQuery->select('box_count')
            ->from('vocabulary_collections')
            ->where($collectionQuery->expr()->eq('id', $collectionQuery->createNamedParameter($collectionId)))
            ->andWhere($collectionQuery->expr()->eq('user_id', $collectionQuery->createNamedParameter($userId)))
            ->executeQuery()
            ->fetchAssociative();
        if ($collection === false) {
            return null;
        }

        $boxCount = (int)$collection['box_count'];
        $entriesQuery = $this->db->getQueryBuilder();
        $entryIds = array_map('intval', $entriesQuery->select('id')
            ->from('vocabulary_entries')
            ->where($entriesQuery->expr()->eq('collection_id', $entriesQuery->createNamedParameter($collectionId)))
            ->executeQuery()
            ->fetchFirstColumn());

        $directions = ['source_to_target', 'target_to_source'];
        $boxCounts = [];
        $entryProgress = [];
        foreach ($directions as $direction) {
            $boxCounts[$direction] = array_fill(1, $boxCount, 0);
        }
        foreach ($entryIds as $entryId) {
            $entryProgress[$entryId] = ['source_to_target' => 1, 'target_to_source' => 1];
            $boxCounts['source_to_target'][1]++;
            $boxCounts['target_to_source'][1]++;
        }

        $totalAnswered = 0;
        $totalCorrect = 0;
        $progressScore = 0.0;
        if ($entryIds !== []) {
            $progressQuery = $this->db->getQueryBuilder();
            $progressRows = $progressQuery->select('entry_id', 'direction', 'box', 'answered', 'answered_correctly')
                ->from('vocabulary_progress')
                ->where($progressQuery->expr()->eq('user_id', $progressQuery->createNamedParameter($userId)))
                ->andWhere($progressQuery->expr()->in(
                    'entry_id',
                    $progressQuery->createNamedParameter($entryIds, IQueryBuilder::PARAM_INT_ARRAY)
                ))
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($progressRows as $row) {
                $entryId = (int)$row['entry_id'];
                $direction = $row['direction'];
                if (!isset($entryProgress[$entryId]) || !isset($boxCounts[$direction])) {
                    throw new \RuntimeException('Stored word-pair progress has invalid collection data.');
                }
                $box = min($boxCount, max(1, (int)$row['box']));

                $entryProgress[$entryId][$direction] = $box;
                $boxCounts[$direction][1]--;
                $boxCounts[$direction][$box]++;
                $totalAnswered += (int)$row['answered'];
                $totalCorrect += (int)$row['answered_correctly'];
                $progressScore += ($box - 1) / max(1, $boxCount - 1);
            }
        }

        $totalDirectionEntries = count($entryIds) * count($directions);
        return [
            'boxCount' => $boxCount,
            'boxCounts' => array_map('array_values', $boxCounts),
            'entryProgress' => $entryProgress,
            'accuracyPercent' => $totalAnswered > 0 ? ($totalCorrect / $totalAnswered) * 100 : null,
            'learningProgressPercent' => $totalDirectionEntries > 0
                ? ($progressScore / $totalDirectionEntries) * 100
                : null,
        ];
    }

    public function createEntry(string $userId, int $collectionId, array $data): bool {
        $entry = $this->validateEntry($data);
        if (!$this->ownsCollection($userId, $collectionId)) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');
        $query = $this->db->getQueryBuilder();
        $query->insert('vocabulary_entries')
            ->values([
                'collection_id' => $query->createNamedParameter($collectionId),
                'source_term' => $query->createNamedParameter($entry['sourceTerm']),
                'target_term' => $query->createNamedParameter($entry['targetTerm']),
                'notes' => $query->createNamedParameter($entry['notes']),
                'created_at' => $query->createNamedParameter($now),
                'updated_at' => $query->createNamedParameter($now),
            ])
            ->executeStatement();

        return true;
    }

    public function updateEntry(string $userId, int $collectionId, int $entryId, array $data): bool {
        $entry = $this->validateEntry($data);
        if (!$this->ownsCollection($userId, $collectionId)) {
            return false;
        }

        $query = $this->db->getQueryBuilder();
        $affectedRows = $query->update('vocabulary_entries')
            ->set('source_term', $query->createNamedParameter($entry['sourceTerm']))
            ->set('target_term', $query->createNamedParameter($entry['targetTerm']))
            ->set('notes', $query->createNamedParameter($entry['notes']))
            ->set('updated_at', $query->createNamedParameter(gmdate('Y-m-d H:i:s')))
            ->where($query->expr()->eq('id', $query->createNamedParameter($entryId)))
            ->andWhere($query->expr()->eq('collection_id', $query->createNamedParameter($collectionId)))
            ->executeStatement();
        if ($affectedRows > 0) {
            return true;
        }

        $check = $this->db->getQueryBuilder();
        return $check->select('id')
            ->from('vocabulary_entries')
            ->where($check->expr()->eq('id', $check->createNamedParameter($entryId)))
            ->andWhere($check->expr()->eq('collection_id', $check->createNamedParameter($collectionId)))
            ->executeQuery()
            ->fetchOne() !== false;
    }

    public function deleteEntries(string $userId, int $collectionId, array $entryIds): ?int {
        $entryIds = $this->validateEntryIds($entryIds);
        if (!$this->ownsCollection($userId, $collectionId)) {
            return null;
        }
        $this->assertEntriesBelongToCollection($collectionId, $entryIds);

        $query = $this->db->getQueryBuilder();
        return $query->delete('vocabulary_entries')
            ->where($query->expr()->eq('collection_id', $query->createNamedParameter($collectionId)))
            ->andWhere($query->expr()->in(
                'id',
                $query->createNamedParameter($entryIds, IQueryBuilder::PARAM_INT_ARRAY)
            ))
            ->executeStatement();
    }

    public function deleteEntry(string $userId, int $collectionId, int $entryId): bool {
        return $this->deleteEntries($userId, $collectionId, [$entryId]) === 1;
    }

    public function moveEntries(string $userId, int $sourceCollectionId, int $targetCollectionId, array $entryIds): ?int {
        $entryIds = $this->validateEntryIds($entryIds);
        if ($sourceCollectionId === $targetCollectionId) {
            throw new InvalidArgumentException('Choose a different destination collection.');
        }
        if (!$this->ownsCollection($userId, $sourceCollectionId) || !$this->ownsCollection($userId, $targetCollectionId)) {
            return null;
        }
        $this->assertEntriesBelongToCollection($sourceCollectionId, $entryIds);

        $query = $this->db->getQueryBuilder();
        return $query->update('vocabulary_entries')
            ->set('collection_id', $query->createNamedParameter($targetCollectionId))
            ->set('updated_at', $query->createNamedParameter(gmdate('Y-m-d H:i:s')))
            ->where($query->expr()->eq('collection_id', $query->createNamedParameter($sourceCollectionId)))
            ->andWhere($query->expr()->in(
                'id',
                $query->createNamedParameter($entryIds, IQueryBuilder::PARAM_INT_ARRAY)
            ))
            ->executeStatement();
    }

    private function ownsCollection(string $userId, int $collectionId): bool {
        $query = $this->db->getQueryBuilder();
        return $query->select('id')
            ->from('vocabulary_collections')
            ->where($query->expr()->eq('id', $query->createNamedParameter($collectionId)))
            ->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->executeQuery()
            ->fetchOne() !== false;
    }

    private function assertEntriesBelongToCollection(int $collectionId, array $entryIds): void {
        $query = $this->db->getQueryBuilder();
        $foundIds = $query->select('id')
            ->from('vocabulary_entries')
            ->where($query->expr()->eq('collection_id', $query->createNamedParameter($collectionId)))
            ->andWhere($query->expr()->in(
                'id',
                $query->createNamedParameter($entryIds, IQueryBuilder::PARAM_INT_ARRAY)
            ))
            ->executeQuery()
            ->fetchFirstColumn();

        if (count($foundIds) !== count($entryIds)) {
            throw new InvalidArgumentException('One or more selected entries are no longer in this collection.');
        }
    }

    private function validateEntry(array $data): array {
        $sourceTerm = $data['sourceTerm'] ?? null;
        $targetTerm = $data['targetTerm'] ?? null;
        $notes = $data['notes'] ?? '';

        if (!is_string($sourceTerm) || trim($sourceTerm) === '' || strlen(trim($sourceTerm)) > 65535) {
            throw new InvalidArgumentException('Enter a source term of up to 65,535 bytes.');
        }
        if (!is_string($targetTerm) || trim($targetTerm) === '' || strlen(trim($targetTerm)) > 65535) {
            throw new InvalidArgumentException('Enter a target term of up to 65,535 bytes.');
        }
        if (!is_string($notes) || strlen(trim($notes)) > 65535) {
            throw new InvalidArgumentException('Notes must be text of up to 65,535 bytes.');
        }

        return [
            'sourceTerm' => trim($sourceTerm),
            'targetTerm' => trim($targetTerm),
            'notes' => trim($notes) === '' ? null : trim($notes),
        ];
    }

    private function validateEntryIds(array $entryIds): array {
        if ($entryIds === [] || count($entryIds) > 500) {
            throw new InvalidArgumentException('Select between 1 and 500 entries.');
        }

        $normalized = [];
        foreach ($entryIds as $entryId) {
            $id = is_int($entryId) || is_string($entryId)
                ? filter_var($entryId, FILTER_VALIDATE_INT)
                : false;
            if ($id === false || $id < 1) {
                throw new InvalidArgumentException('The selected entry IDs are invalid.');
            }
            $normalized[] = $id;
        }

        $normalized = array_values(array_unique($normalized));
        if (count($normalized) !== count($entryIds)) {
            throw new InvalidArgumentException('The selected entries must be unique.');
        }
        return $normalized;
    }

    private function validateCollection(array $data): array {
        $name = $data['name'] ?? null;
        $sourceLanguage = $data['sourceLanguage'] ?? null;
        $targetLanguage = $data['targetLanguage'] ?? null;
        $rawBoxCount = $data['boxCount'] ?? null;

        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 255) {
            throw new InvalidArgumentException('Enter a collection name of up to 255 characters.');
        }
        if (!is_string($sourceLanguage) || !$this->isLanguageTag(trim($sourceLanguage))) {
            throw new InvalidArgumentException('Enter a valid source language tag.');
        }
        if (!is_string($targetLanguage) || !$this->isLanguageTag(trim($targetLanguage))) {
            throw new InvalidArgumentException('Enter a valid target language tag.');
        }
        if (strcasecmp(trim($sourceLanguage), trim($targetLanguage)) === 0) {
            throw new InvalidArgumentException('Choose different source and target languages.');
        }

        $boxCount = is_int($rawBoxCount) || is_string($rawBoxCount)
            ? filter_var($rawBoxCount, FILTER_VALIDATE_INT)
            : false;
        if ($boxCount === false || $boxCount < 2 || $boxCount > 10) {
            throw new InvalidArgumentException('Choose between 2 and 10 study boxes.');
        }

        return [
            'name' => trim($name),
            'sourceLanguage' => trim($sourceLanguage),
            'targetLanguage' => trim($targetLanguage),
            'boxCount' => $boxCount,
        ];
    }

    private function isLanguageTag(string $languageTag): bool {
        return strlen($languageTag) <= 64
            && preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/D', $languageTag) === 1;
    }
}
