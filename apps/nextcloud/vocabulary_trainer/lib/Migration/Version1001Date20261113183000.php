<?php

declare(strict_types=1);

namespace OCA\VocabularyTrainer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1001Date20261113183000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('vocabulary_user_settings')) {
            $table = $schema->getTable('vocabulary_user_settings');
            if (!$table->hasColumn('answer_delay_seconds')) {
                $table->addColumn('answer_delay_seconds', 'integer', [
                    'default' => 5,
                    'notnull' => true,
                ]);
            }
            if (!$table->hasColumn('question_time_limit_seconds')) {
                $table->addColumn('question_time_limit_seconds', 'integer', [
                    'default' => 0,
                    'notnull' => true,
                ]);
            }
        }

        return $schema;
    }
}
