<?php

declare(strict_types=1);

namespace OCP {
    interface IDBConnection {
    }

    interface IRequest {
        public function getParam(string $key, mixed $default = null): mixed;
    }

    interface IUser {
        public function getUID(): string;
    }

    interface IUserSession {
        public function getUser(): ?IUser;
    }
}

namespace OCP\DB\QueryBuilder {
    interface IQueryBuilder {
        public const PARAM_INT_ARRAY = 101;
    }
}

namespace OCP\AppFramework {
    use OCP\IRequest;

    class Controller {
        protected IRequest $request;

        public function __construct(string $appName, IRequest $request) {
            $this->request = $request;
        }
    }
}

namespace OCP\AppFramework {
    class Http {
        public const STATUS_OK = 200;
        public const STATUS_CREATED = 201;
        public const STATUS_BAD_REQUEST = 400;
        public const STATUS_UNAUTHORIZED = 401;
        public const STATUS_NOT_FOUND = 404;
    }
}

namespace OCP\AppFramework\Http {
    use OCP\AppFramework\Http as HttpStatus;

    class DataResponse {
        public function __construct(private mixed $data = [], private int $status = HttpStatus::STATUS_OK) {
        }

        public function getData(): mixed {
            return $this->data;
        }

        public function getStatus(): int {
            return $this->status;
        }
    }
}

namespace OCP\AppFramework\Http\Attribute {
    use Attribute;

    #[Attribute(Attribute::TARGET_METHOD)]
    final class NoAdminRequired {
    }
}

namespace OCA\VocabularyTrainer\Tests {
    use InvalidArgumentException;
    use OCA\VocabularyTrainer\Controller\CollectionController;
    use OCA\VocabularyTrainer\Service\CollectionService;
    use OCP\IDBConnection;
    use OCP\IRequest;
    use OCP\IUser;
    use OCP\IUserSession;
    use RuntimeException;

    require_once __DIR__ . '/../lib/Service/CollectionService.php';
    require_once __DIR__ . '/../lib/Controller/CollectionController.php';

    final class FakeResult {
        public function __construct(private array $rows = [], private mixed $firstValue = false) {
        }

        public function fetchAllAssociative(): array {
            return $this->rows;
        }

        public function fetchOne(): mixed {
            return $this->firstValue;
        }
    }

    final class FakeExpressionBuilder {
        public function eq(string $column, string $parameter): string {
            return $column . ' = ' . $parameter;
        }

        public function in(string $column, string $parameter): string {
            return $column . ' IN ' . $parameter;
        }
    }

    final class FakeQueryBuilder {
        public array $operations = [];
        public array $parameters = [];
        public int $affectedRows = 1;
        public array $rows = [];
        public mixed $firstValue = false;
        private int $parameterId = 0;

        public function expr(): FakeExpressionBuilder {
            return new FakeExpressionBuilder();
        }

        public function createNamedParameter(mixed $value): string {
            $name = ':param' . $this->parameterId++;
            $this->parameters[$name] = $value;
            return $name;
        }

        public function select(string ...$columns): self {
            $this->operations[] = ['select', $columns];
            return $this;
        }

        public function from(string $table, ?string $alias = null): self {
            $this->operations[] = ['from', $table, $alias];
            return $this;
        }

        public function leftJoin(string $fromAlias, string $join, string $alias, string $condition): self {
            $this->operations[] = ['leftJoin', $fromAlias, $join, $alias, $condition];
            return $this;
        }

        public function where(string $condition): self {
            $this->operations[] = ['where', $condition];
            return $this;
        }

        public function andWhere(string $condition): self {
            $this->operations[] = ['andWhere', $condition];
            return $this;
        }

        public function groupBy(string ...$columns): self {
            $this->operations[] = ['groupBy', $columns];
            return $this;
        }

        public function orderBy(string $column, string $direction): self {
            $this->operations[] = ['orderBy', $column, $direction];
            return $this;
        }

        public function insert(string $table): self {
            $this->operations[] = ['insert', $table];
            return $this;
        }

        public function values(array $values): self {
            $this->operations[] = ['values', $values];
            return $this;
        }

        public function update(string $table): self {
            $this->operations[] = ['update', $table];
            return $this;
        }

        public function set(string $column, string $value): self {
            $this->operations[] = ['set', $column, $value];
            return $this;
        }

        public function delete(string $table): self {
            $this->operations[] = ['delete', $table];
            return $this;
        }

        public function executeStatement(): int {
            $this->operations[] = ['executeStatement'];
            return $this->affectedRows;
        }

        public function executeQuery(): FakeResult {
            $this->operations[] = ['executeQuery'];
            return new FakeResult($this->rows, $this->firstValue);
        }
    }

    final class FakeDatabase implements IDBConnection {
        public array $builders = [];
        public array $nextBuilders = [];

        public function getQueryBuilder(): FakeQueryBuilder {
            $builder = array_shift($this->nextBuilders) ?? new FakeQueryBuilder();
            $this->builders[] = $builder;
            return $builder;
        }
    }

    final class FakeRequest implements IRequest {
        public function __construct(private array $params = []) {
        }

        public function getParam(string $key, mixed $default = null): mixed {
            return $this->params[$key] ?? $default;
        }
    }

    final class FakeUser implements IUser {
        public function __construct(private string $userId) {
        }

        public function getUID(): string {
            return $this->userId;
        }
    }

    final class FakeUserSession implements IUserSession {
        public function __construct(private ?IUser $user) {
        }

        public function getUser(): ?IUser {
            return $this->user;
        }
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void {
        if ($expected !== $actual) {
            throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
        }
    }

    function assertTrue(bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    function assertInvalidCollection(CollectionService $service, array $data, string $message): void {
        try {
            $service->createCollection('alice', $data);
        } catch (InvalidArgumentException) {
            return;
        }
        throw new RuntimeException($message);
    }

    $passed = 0;
    $test = static function (string $name, callable $case) use (&$passed): void {
        $case();
        ++$passed;
        echo "PASS: {$name}\n";
    };

    $test('lists normalized collection records and counts', static function (): void {
        $db = new FakeDatabase();
        $collectionQuery = new FakeQueryBuilder();
        $collectionQuery->rows = [[
            'id' => '12',
            'name' => 'French',
            'source_language' => 'fr',
            'target_language' => 'de',
            'box_count' => '4',
        ]];
        $entryQuery = new FakeQueryBuilder();
        $entryQuery->rows = array_fill(0, 9, ['collection_id' => '12']);
        $db->nextBuilders = [$collectionQuery, $entryQuery];
        $service = new CollectionService($db);

        assertSameValue([[
            'id' => 12,
            'name' => 'French',
            'sourceLanguage' => 'fr',
            'targetLanguage' => 'de',
            'boxCount' => 4,
            'entryCount' => 9,
        ]], $service->getCollections('alice'), 'Collection output should use the UI shape.');
        assertTrue(in_array(['where', 'c.user_id = :param0'], $collectionQuery->operations, true), 'List query must scope by owner.');
        assertSameValue('alice', $collectionQuery->parameters[':param0'], 'The owner ID must be bound as a parameter.');
        assertTrue(in_array(['where', 'collection_id IN :param0'], $entryQuery->operations, true), 'Entry counts must only query returned collection IDs.');
        assertSameValue([12], $entryQuery->parameters[':param0'], 'Collection IDs must be bound as an integer array.');
    });

    $test('does not query entries when the user has no collections', static function (): void {
        $db = new FakeDatabase();
        $query = new FakeQueryBuilder();
        $db->nextBuilders[] = $query;
        $service = new CollectionService($db);

        assertSameValue([], $service->getCollections('alice'), 'An account without collections should get an empty list.');
        assertSameValue(1, count($db->builders), 'No entry query is needed for an empty collection list.');
    });

    $test('creates a normalized collection for the supplied owner', static function (): void {
        $db = new FakeDatabase();
        $service = new CollectionService($db);
        $service->createCollection('alice', [
            'name' => '  French  ',
            'sourceLanguage' => ' fr ',
            'targetLanguage' => 'de',
            'boxCount' => '4',
        ]);

        $builder = $db->builders[0];
        assertTrue(in_array(['insert', 'vocabulary_collections'], $builder->operations, true), 'Create must insert a collection row.');
        assertSameValue('alice', $builder->parameters[':param0'], 'Create must set the owner from the caller.');
        assertSameValue('French', $builder->parameters[':param1'], 'Create must trim the collection name.');
        assertSameValue('fr', $builder->parameters[':param2'], 'Create must trim the source language.');
        assertSameValue('de', $builder->parameters[':param3'], 'Create must persist the target language.');
        assertSameValue(4, $builder->parameters[':param4'], 'Create must persist an integer box count.');
        assertTrue(isset($builder->parameters[':param5'], $builder->parameters[':param6']), 'Create must set created and updated timestamps.');
        assertSameValue($builder->parameters[':param5'], $builder->parameters[':param6'], 'Create timestamps should match.');
    });

    $test('updates only a collection owned by the supplied user', static function (): void {
        $db = new FakeDatabase();
        $builder = new FakeQueryBuilder();
        $builder->affectedRows = 1;
        $db->nextBuilders[] = $builder;
        $service = new CollectionService($db);

        assertTrue($service->updateCollection('alice', 7, [
            'name' => 'French',
            'sourceLanguage' => 'fr',
            'targetLanguage' => 'de',
            'boxCount' => 5,
        ]), 'An updated collection should return true.');
        assertTrue(in_array(['where', 'id = :param5'], $builder->operations, true), 'Update must constrain the collection ID.');
        assertTrue(in_array(['andWhere', 'user_id = :param6'], $builder->operations, true), 'Update must constrain the owner ID.');
        assertSameValue(7, $builder->parameters[':param5'], 'Update must bind the collection ID.');
        assertSameValue('alice', $builder->parameters[':param6'], 'Update must bind the owner ID.');
    });

    $test('returns not found when updating another user collection', static function (): void {
        $db = new FakeDatabase();
        $update = new FakeQueryBuilder();
        $update->affectedRows = 0;
        $db->nextBuilders[] = $update;
        $check = new FakeQueryBuilder();
        $check->firstValue = false;
        $db->nextBuilders[] = $check;
        $service = new CollectionService($db);

        assertSameValue(false, $service->updateCollection('alice', 7, [
            'name' => 'French',
            'sourceLanguage' => 'fr',
            'targetLanguage' => 'de',
            'boxCount' => 5,
        ]), 'An unavailable collection should not be reported as updated.');
        assertTrue(in_array(['andWhere', 'user_id = :param1'], $check->operations, true), 'Existence check must also constrain the owner.');
    });

    $test('deletes only a collection owned by the supplied user', static function (): void {
        $db = new FakeDatabase();
        $builder = new FakeQueryBuilder();
        $builder->affectedRows = 1;
        $db->nextBuilders[] = $builder;
        $service = new CollectionService($db);

        assertTrue($service->deleteCollection('alice', 7), 'A deleted collection should return true.');
        assertTrue(in_array(['delete', 'vocabulary_collections'], $builder->operations, true), 'Delete must target collections.');
        assertTrue(in_array(['where', 'id = :param0'], $builder->operations, true), 'Delete must constrain the collection ID.');
        assertTrue(in_array(['andWhere', 'user_id = :param1'], $builder->operations, true), 'Delete must constrain the owner ID.');
        assertSameValue('alice', $builder->parameters[':param1'], 'Delete must bind the owner ID.');
    });

    $test('rejects invalid collection data before database access', static function (): void {
        $invalidCollections = [
            [['name' => ' ', 'sourceLanguage' => 'en', 'targetLanguage' => 'de', 'boxCount' => 5], 'blank name'],
            [['name' => str_repeat('a', 256), 'sourceLanguage' => 'en', 'targetLanguage' => 'de', 'boxCount' => 5], 'long name'],
            [['name' => 'Words', 'sourceLanguage' => 'x', 'targetLanguage' => 'de', 'boxCount' => 5], 'invalid language tag'],
            [['name' => 'Words', 'sourceLanguage' => 'en', 'targetLanguage' => 'EN', 'boxCount' => 5], 'same languages'],
            [['name' => 'Words', 'sourceLanguage' => 'en', 'targetLanguage' => 'de', 'boxCount' => 1], 'too few study boxes'],
            [['name' => 'Words', 'sourceLanguage' => 'en', 'targetLanguage' => 'de', 'boxCount' => 11], 'too many study boxes'],
            [['name' => 'Words', 'sourceLanguage' => 'en', 'targetLanguage' => 'de', 'boxCount' => '5abc'], 'non-integer study boxes'],
        ];

        foreach ($invalidCollections as [$data, $case]) {
            $db = new FakeDatabase();
            assertInvalidCollection(new CollectionService($db), $data, "Expected {$case} to be rejected.");
            assertSameValue([], $db->builders, "Invalid {$case} must not reach the database.");
        }
    });

    $test('returns unauthorized when the request has no signed-in user', static function (): void {
        $controller = new CollectionController(
            'vocabulary_trainer',
            new FakeRequest(),
            new CollectionService(new FakeDatabase()),
            new FakeUserSession(null),
        );
        $response = $controller->index();

        assertSameValue(401, $response->getStatus(), 'An anonymous request must be rejected.');
    });

    $test('creates collections for the session user, not a request-supplied user', static function (): void {
        $db = new FakeDatabase();
        $controller = new CollectionController(
            'vocabulary_trainer',
            new FakeRequest([
                'name' => 'French',
                'sourceLanguage' => 'fr',
                'targetLanguage' => 'de',
                'boxCount' => '5',
                'userId' => 'mallory',
            ]),
            new CollectionService($db),
            new FakeUserSession(new FakeUser('alice')),
        );
        $response = $controller->create();

        assertSameValue(201, $response->getStatus(), 'A valid create request should return created.');
        assertSameValue('alice', $db->builders[0]->parameters[':param0'], 'The owner must come from the session.');
    });

    $test('returns a client error for invalid collection requests', static function (): void {
        $db = new FakeDatabase();
        $controller = new CollectionController(
            'vocabulary_trainer',
            new FakeRequest([
                'name' => 'French',
                'sourceLanguage' => 'fr',
                'targetLanguage' => 'FR',
                'boxCount' => '5',
            ]),
            new CollectionService($db),
            new FakeUserSession(new FakeUser('alice')),
        );
        $response = $controller->create();

        assertSameValue(400, $response->getStatus(), 'Invalid data should return a bad request.');
        assertSameValue([], $db->builders, 'Invalid requests must not access the database.');
    });

    echo "{$passed} collection API tests passed.\n";
}
