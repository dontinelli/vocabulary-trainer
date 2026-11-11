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

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'sourceLanguage' => $row['source_language'],
            'targetLanguage' => $row['target_language'],
            'boxCount' => (int)$row['box_count'],
            'entryCount' => $entryCounts[(int)$row['id']] ?? 0,
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
