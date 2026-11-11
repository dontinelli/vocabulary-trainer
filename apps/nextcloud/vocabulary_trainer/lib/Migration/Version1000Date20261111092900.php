<?php

namespace OCA\VocabularyTrainer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261111092900 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('vocabulary_collections')) {
            $schema->getTable('vocabulary_collections')->modifyColumn('id', [
                'length' => 20,
                'unsigned' => true,
                'autoincrement' => true,
                'notnull' => true,
            ]);
        }

        if (!$schema->hasTable('vocabulary_collections')) {
            $table = $schema->createTable('vocabulary_collections');
            $table->addColumn('id', 'bigint', [
                'length' => 20,
                'unsigned' => true,
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('user_id', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('source_language', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('target_language', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('box_count', 'integer', ['default' => 5, 'notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'vocab_coll_user_idx');
        }

        if (!$schema->hasTable('vocabulary_entries')) {
            $table = $schema->createTable('vocabulary_entries');
            $table->addColumn('id', 'bigint', [
                'length' => 20,
                'unsigned' => true,
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('collection_id', 'bigint', [
                'length' => 20,
                'unsigned' => true,
                'notnull' => true,
            ]);
            $table->addColumn('source_term', 'text', ['notnull' => true]);
            $table->addColumn('target_term', 'text', ['notnull' => true]);
            $table->addColumn('notes', 'text', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['collection_id'], 'vocab_entry_coll_idx');
            $table->addForeignKeyConstraint(
                'vocabulary_collections',
                ['collection_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'vocab_entry_coll_fk'
            );
        }

        if (!$schema->hasTable('vocabulary_progress')) {
            $table = $schema->createTable('vocabulary_progress');
            $table->addColumn('id', 'bigint', [
                'length' => 20,
                'unsigned' => true,
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('user_id', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('entry_id', 'bigint', [
                'length' => 20,
                'unsigned' => true,
                'notnull' => true,
            ]);
            $table->addColumn('direction', 'string', ['length' => 32, 'notnull' => true]);
            $table->addColumn('box', 'integer', ['default' => 1, 'notnull' => true]);
            $table->addColumn('answered', 'integer', ['default' => 0, 'notnull' => true]);
            $table->addColumn('answered_correctly', 'integer', ['default' => 0, 'notnull' => true]);
            $table->addColumn('last_answered_at', 'datetime', ['notnull' => false]);
            $table->addColumn('next_ask_at', 'datetime', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id', 'entry_id', 'direction'], 'vocab_progress_user_entry_dir_uq');
            $table->addIndex(['user_id', 'next_ask_at'], 'vocab_progress_due_idx');
            $table->addForeignKeyConstraint(
                'vocabulary_entries',
                ['entry_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'vocab_progress_entry_fk'
            );
        }

        if (!$schema->hasTable('vocabulary_user_settings')) {
            $table = $schema->createTable('vocabulary_user_settings');
            $table->addColumn('user_id', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('start_with_new', 'boolean', ['notnull' => true]);
            $table->addColumn('include_final_box', 'boolean', ['notnull' => true]);
            $table->addColumn('return_by_mistake', 'boolean', ['notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['user_id']);
        }

        return $schema;
    }
}
