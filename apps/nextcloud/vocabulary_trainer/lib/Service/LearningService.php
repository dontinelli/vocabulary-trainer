<?php

declare(strict_types=1);

namespace OCA\VocabularyTrainer\Service;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class LearningService {
    private const DEFAULT_SETTINGS = [
        'startWithNew' => true,
        'includeFinalBox' => false,
    ];

    private const BOX_INTERVALS = [
        1 => null,
        2 => 1,
        3 => 3,
        4 => 7,
        5 => 15,
        6 => 30,
        7 => 60,
        8 => 120,
        9 => 240,
        10 => 480,
    ];

    public function __construct(
        private IDBConnection $db,
    ) {
    }

    public function getSettings(string $userId, int $collectionId): ?array {
        if ($this->getOwnedCollection($userId, $collectionId) === null) {
            return null;
        }

        $query = $this->db->getQueryBuilder();
        $row = $query->select('start_with_new', 'include_final_box')
            ->from('vocabulary_user_settings')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return self::DEFAULT_SETTINGS;
        }

        return [
            'startWithNew' => (bool)$row['start_with_new'],
            'includeFinalBox' => (bool)$row['include_final_box'],
        ];
    }

    public function startSession(string $userId, int $collectionId, array $data): ?array {
        $config = $this->validateSessionConfig($data);
        $collection = $this->getOwnedCollection($userId, $collectionId);
        if ($collection === null) {
            return null;
        }

        $settings = [
            'startWithNew' => $config['startWithNew'],
            'includeFinalBox' => $config['includeFinalBox'],
        ];
        $entries = $this->getCollectionEntries($collectionId);
        if ($config['selection'] === 'box' && $config['box'] > (int)$collection['box_count']) {
            throw new InvalidArgumentException('Choose a box that exists in this collection.');
        }
        $progress = $this->getProgress($userId, array_column($entries, 'id'), $config['direction']);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $questions = $this->selectEntries(
            $entries,
            $progress,
            $config,
            $settings,
            (int)$collection['box_count'],
            $now
        );

        if (in_array($config['mode'], ['multiple-choice', 'mixed'], true) && count($questions) <= 10) {
            throw new InvalidArgumentException('Multiple-choice and mixed modes require at least 11 matching word pairs.');
        }

        $this->saveSettings($userId, $settings);
        return [
            'direction' => $config['direction'],
            'mode' => $config['mode'],
            'totalQuestions' => count($questions),
            'questions' => $this->makeQuestions($entries, $questions, $config),
        ];
    }

    public function answer(string $userId, int $collectionId, int $entryId, array $data): ?array {
        $direction = $this->validateDirection($data['direction'] ?? null);
        $answer = $data['answer'] ?? null;
        if (!is_string($answer) || trim($answer) === '' || strlen(trim($answer)) > 65535) {
            throw new InvalidArgumentException('Enter an answer of up to 65,535 bytes.');
        }

        $collection = $this->getOwnedCollection($userId, $collectionId);
        if ($collection === null) {
            return null;
        }

        $query = $this->db->getQueryBuilder();
        $entry = $query->select('source_term', 'target_term')
            ->from('vocabulary_entries')
            ->where($query->expr()->eq('id', $query->createNamedParameter($entryId)))
            ->andWhere($query->expr()->eq('collection_id', $query->createNamedParameter($collectionId)))
            ->executeQuery()
            ->fetchAssociative();
        if ($entry === false) {
            return null;
        }

        $correctAnswer = $direction === 'source_to_target' ? $entry['target_term'] : $entry['source_term'];
        $isCorrect = $this->normalizeAnswer($answer) === $this->normalizeAnswer($correctAnswer);

        $progress = $this->getEntryProgress($userId, $entryId, $direction);
        $boxCount = (int)$collection['box_count'];
        $currentBox = $progress === null ? 1 : (int)$progress['box'];
        $newBox = $isCorrect ? min($boxCount, $currentBox + 1) : 1;
        $answered = $progress === null ? 1 : (int)$progress['answered'] + 1;
        $answeredCorrectly = ($progress === null ? 0 : (int)$progress['answered_correctly']) + ($isCorrect ? 1 : 0);
        $answeredAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nextAskAt = $this->nextAskAt($newBox, $boxCount, $this->getUserSettings($userId)['includeFinalBox'], $answeredAt);
        $this->writeProgress(
            $userId,
            $entryId,
            $direction,
            $newBox,
            $answered,
            $answeredCorrectly,
            $answeredAt,
            $nextAskAt,
            $progress !== null
        );

        return [
            'correct' => $isCorrect,
            'correctAnswer' => $correctAnswer,
            'box' => $newBox,
            'answered' => $answered,
            'answeredCorrectly' => $answeredCorrectly,
        ];
    }

    private function getOwnedCollection(string $userId, int $collectionId): ?array {
        $query = $this->db->getQueryBuilder();
        $row = $query->select('id', 'box_count')
            ->from('vocabulary_collections')
            ->where($query->expr()->eq('id', $query->createNamedParameter($collectionId)))
            ->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    private function getUserSettings(string $userId): array {
        $query = $this->db->getQueryBuilder();
        $row = $query->select('start_with_new', 'include_final_box')
            ->from('vocabulary_user_settings')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return self::DEFAULT_SETTINGS;
        }

        return [
            'startWithNew' => (bool)$row['start_with_new'],
            'includeFinalBox' => (bool)$row['include_final_box'],
        ];
    }

    private function saveSettings(string $userId, array $settings): void {
        $query = $this->db->getQueryBuilder();
        $existing = $query->select('user_id')
            ->from('vocabulary_user_settings')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->executeQuery()
            ->fetchOne();
        $now = gmdate('Y-m-d H:i:s');

        if ($existing === false) {
            $query = $this->db->getQueryBuilder();
            $query->insert('vocabulary_user_settings')
                ->values([
                    'user_id' => $query->createNamedParameter($userId),
                    'start_with_new' => $query->createNamedParameter((int)$settings['startWithNew'], IQueryBuilder::PARAM_INT),
                    'include_final_box' => $query->createNamedParameter((int)$settings['includeFinalBox'], IQueryBuilder::PARAM_INT),
                    'return_by_mistake' => $query->createNamedParameter(0, IQueryBuilder::PARAM_INT),
                    'created_at' => $query->createNamedParameter($now),
                    'updated_at' => $query->createNamedParameter($now),
                ])
                ->executeStatement();
            return;
        }

        $query = $this->db->getQueryBuilder();
        $query->update('vocabulary_user_settings')
            ->set('start_with_new', $query->createNamedParameter((int)$settings['startWithNew'], IQueryBuilder::PARAM_INT))
            ->set('include_final_box', $query->createNamedParameter((int)$settings['includeFinalBox'], IQueryBuilder::PARAM_INT))
            ->set('updated_at', $query->createNamedParameter($now))
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->executeStatement();
    }

    private function getCollectionEntries(int $collectionId): array {
        $query = $this->db->getQueryBuilder();
        return $query->select('id', 'source_term', 'target_term')
            ->from('vocabulary_entries')
            ->where($query->expr()->eq('collection_id', $query->createNamedParameter($collectionId)))
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function getProgress(string $userId, array $entryIds, string $direction): array {
        if ($entryIds === []) {
            return [];
        }
        $query = $this->db->getQueryBuilder();
        $rows = $query->select('entry_id', 'box', 'answered', 'answered_correctly', 'last_answered_at', 'next_ask_at')
            ->from('vocabulary_progress')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->andWhere($query->expr()->eq('direction', $query->createNamedParameter($direction)))
            ->andWhere($query->expr()->in(
                'entry_id',
                $query->createNamedParameter(array_map('intval', $entryIds), IQueryBuilder::PARAM_INT_ARRAY)
            ))
            ->executeQuery()
            ->fetchAllAssociative();

        $progress = [];
        foreach ($rows as $row) {
            $progress[(int)$row['entry_id']] = $row;
        }
        return $progress;
    }

    private function getEntryProgress(string $userId, int $entryId, string $direction): ?array {
        $query = $this->db->getQueryBuilder();
        $row = $query->select('box', 'answered', 'answered_correctly')
            ->from('vocabulary_progress')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->andWhere($query->expr()->eq('entry_id', $query->createNamedParameter($entryId)))
            ->andWhere($query->expr()->eq('direction', $query->createNamedParameter($direction)))
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }

    private function writeProgress(
        string $userId,
        int $entryId,
        string $direction,
        int $box,
        int $answered,
        int $answeredCorrectly,
        DateTimeImmutable $answeredAt,
        ?DateTimeImmutable $nextAskAt,
        bool $exists,
    ): void {
        $now = $answeredAt->format('Y-m-d H:i:s');
        $nextAsk = $nextAskAt?->format('Y-m-d H:i:s');
        $query = $this->db->getQueryBuilder();

        if ($exists) {
            $query->update('vocabulary_progress')
                ->set('box', $query->createNamedParameter($box))
                ->set('answered', $query->createNamedParameter($answered))
                ->set('answered_correctly', $query->createNamedParameter($answeredCorrectly))
                ->set('last_answered_at', $query->createNamedParameter($now))
                ->set('next_ask_at', $query->createNamedParameter($nextAsk))
                ->set('updated_at', $query->createNamedParameter($now))
                ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
                ->andWhere($query->expr()->eq('entry_id', $query->createNamedParameter($entryId)))
                ->andWhere($query->expr()->eq('direction', $query->createNamedParameter($direction)))
                ->executeStatement();
            return;
        }

        $query->insert('vocabulary_progress')
            ->values([
                'user_id' => $query->createNamedParameter($userId),
                'entry_id' => $query->createNamedParameter($entryId),
                'direction' => $query->createNamedParameter($direction),
                'box' => $query->createNamedParameter($box),
                'answered' => $query->createNamedParameter($answered),
                'answered_correctly' => $query->createNamedParameter($answeredCorrectly),
                'last_answered_at' => $query->createNamedParameter($now),
                'next_ask_at' => $query->createNamedParameter($nextAsk),
                'created_at' => $query->createNamedParameter($now),
                'updated_at' => $query->createNamedParameter($now),
            ])
            ->executeStatement();
    }

    private function selectEntries(
        array $entries,
        array $progress,
        array $config,
        array $settings,
        int $boxCount,
        DateTimeImmutable $now,
    ): array {
        $selected = [];
        foreach ($entries as $entry) {
            $id = (int)$entry['id'];
            $state = $progress[$id] ?? null;
            if ($this->matchesSelection($state, $config, $settings, $boxCount, $now)) {
                $selected[] = $entry;
            }
        }

        if ($config['selection'] === 'automatic') {
            $newEntries = [];
            $dueEntries = [];
            foreach ($selected as $entry) {
                if (!isset($progress[(int)$entry['id']])) {
                    $newEntries[] = $entry;
                } else {
                    $dueEntries[] = $entry;
                }
            }
            shuffle($newEntries);
            shuffle($dueEntries);
            if ($settings['startWithNew']) {
                return array_merge($newEntries, $dueEntries);
            }
        }
        shuffle($selected);
        return $selected;
    }

    private function matchesSelection(
        ?array $progress,
        array $config,
        array $settings,
        int $boxCount,
        DateTimeImmutable $now,
    ): bool {
        if ($config['selection'] === 'new') {
            return $progress === null;
        }
        if ($config['selection'] === 'box') {
            return $progress !== null && (int)$progress['box'] === $config['box'];
        }
        if ($config['selection'] === 'error-rate') {
            if ($progress === null || (int)$progress['answered'] === 0) {
                return false;
            }
            $errors = (int)$progress['answered'] - (int)$progress['answered_correctly'];
            return ($errors / (int)$progress['answered']) * 100 > $config['errorRate'];
        }
        if ($config['selection'] === 'not-practiced') {
            if ($progress === null || $progress['last_answered_at'] === null) {
                return true;
            }
            $lastAnswered = new DateTimeImmutable($progress['last_answered_at'], new DateTimeZone('UTC'));
            return $lastAnswered <= $now->modify('-' . $config['days'] . ' days');
        }

        if ($progress === null) {
            return true;
        }
        $box = (int)$progress['box'];
        if ($box < 1 || $box > $boxCount) {
            throw new \RuntimeException('Stored word-pair progress has an invalid box.');
        }
        $isFinalBox = $box === $boxCount;
        if ($isFinalBox && !$settings['includeFinalBox']) {
            return false;
        }
        if ($box === 1) {
            return true;
        }
        if ($progress['next_ask_at'] === null) {
            if ($isFinalBox && $settings['includeFinalBox'] && $progress['last_answered_at'] !== null) {
                $interval = self::BOX_INTERVALS[$box] ?? null;
                if ($interval !== null) {
                    $dueAt = (new DateTimeImmutable($progress['last_answered_at'], new DateTimeZone('UTC')))
                        ->modify('+' . $interval . ' days');
                    return $dueAt <= $now;
                }
            }
            return true;
        }
        return new DateTimeImmutable($progress['next_ask_at'], new DateTimeZone('UTC')) <= $now;
    }

    private function makeQuestions(array $entries, array $selected, array $config): array {
        $byId = [];
        foreach ($entries as $entry) {
            $byId[(int)$entry['id']] = $entry;
        }

        $questions = [];
        foreach ($selected as $entry) {
            $entryId = (int)$entry['id'];
            $prompt = $config['direction'] === 'source_to_target' ? $entry['source_term'] : $entry['target_term'];
            $correctAnswer = $config['direction'] === 'source_to_target' ? $entry['target_term'] : $entry['source_term'];
            $type = $config['mode'] === 'mixed'
                ? (random_int(0, 1) === 0 ? 'typed' : 'multiple-choice')
                : $config['mode'];
            $choices = [];
            if ($type === 'multiple-choice') {
                $choices = $this->makeChoices($byId, $entryId, $correctAnswer, $config['direction']);
                if (count($choices) < 4) {
                    $type = 'typed';
                }
            }
            $questions[] = [
                'entryId' => $entryId,
                'type' => $type,
                'prompt' => $prompt,
                'choices' => $choices,
            ];
        }
        return $questions;
    }

    private function makeChoices(array $entries, int $entryId, string $correctAnswer, string $direction): array {
        $options = [$correctAnswer];
        $seen = [$this->normalizeAnswer($correctAnswer) => true];
        foreach ($entries as $id => $entry) {
            if ($id === $entryId) {
                continue;
            }
            $option = $direction === 'source_to_target' ? $entry['target_term'] : $entry['source_term'];
            $normalized = $this->normalizeAnswer($option);
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $options[] = $option;
        }
        shuffle($options);
        return array_slice($options, 0, 4);
    }

    private function nextAskAt(int $box, int $boxCount, bool $includeFinalBox, DateTimeImmutable $now): ?DateTimeImmutable {
        if ($box === $boxCount && !$includeFinalBox) {
            return null;
        }
        $days = self::BOX_INTERVALS[$box] ?? null;
        return $days === null ? null : $now->modify('+' . $days . ' days');
    }

    private function validateSessionConfig(array $data): array {
        $direction = $this->validateDirection($data['direction'] ?? null);
        $mode = $data['mode'] ?? null;
        if (!in_array($mode, ['typed', 'multiple-choice', 'mixed'], true)) {
            throw new InvalidArgumentException('Choose a valid learning mode.');
        }
        $selection = $data['selection'] ?? null;
        if (!in_array($selection, ['automatic', 'new', 'box', 'error-rate', 'not-practiced'], true)) {
            throw new InvalidArgumentException('Choose a valid word-pair selection.');
        }

        $startWithNew = $this->parseBoolean($data['startWithNew'] ?? self::DEFAULT_SETTINGS['startWithNew']);
        $includeFinalBox = $this->parseBoolean($data['includeFinalBox'] ?? self::DEFAULT_SETTINGS['includeFinalBox']);
        $config = [
            'direction' => $direction,
            'mode' => $mode,
            'selection' => $selection,
            'startWithNew' => $startWithNew,
            'includeFinalBox' => $includeFinalBox,
        ];

        if ($selection === 'box') {
            $box = filter_var($data['box'] ?? null, FILTER_VALIDATE_INT);
            if ($box === false || $box < 1 || $box > 10) {
                throw new InvalidArgumentException('Choose a valid study box.');
            }
            $config['box'] = $box;
        } elseif ($selection === 'error-rate') {
            $errorRate = filter_var($data['errorRate'] ?? null, FILTER_VALIDATE_INT);
            if ($errorRate === false || $errorRate < 0 || $errorRate > 100) {
                throw new InvalidArgumentException('Choose an error-rate threshold from 0 to 100 percent.');
            }
            $config['errorRate'] = $errorRate;
        } elseif ($selection === 'not-practiced') {
            $days = filter_var($data['days'] ?? null, FILTER_VALIDATE_INT);
            if ($days === false || $days < 1 || $days > 3650) {
                throw new InvalidArgumentException('Choose a number of days from 1 to 3,650.');
            }
            $config['days'] = $days;
        }
        return $config;
    }

    private function validateDirection(mixed $direction): string {
        if (!is_string($direction) || !in_array($direction, ['source_to_target', 'target_to_source'], true)) {
            throw new InvalidArgumentException('Choose a valid translation direction.');
        }
        return $direction;
    }

    private function parseBoolean(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }
        throw new InvalidArgumentException('Learning settings must be true or false.');
    }

    private function normalizeAnswer(string $answer): string {
        $collapsed = preg_replace('/\s+/u', ' ', trim($answer));
        if ($collapsed === null) {
            throw new InvalidArgumentException('Answers must be valid UTF-8 text.');
        }
        return mb_strtolower($collapsed, 'UTF-8');
    }
}
