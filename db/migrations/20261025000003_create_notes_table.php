<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateNotesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('notes', [
            'id' => false,
            'primary_key' => 'id',
            'collation' => 'utf8mb4_0900_ai_ci',
            'encoding' => 'utf8mb4',
        ]);

        $table->addColumn('id', 'string', ['limit' => 36, 'null' => false])
            ->addColumn('user_id', 'string', ['limit' => 36, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('content', 'text', ['null' => false])
            ->addColumn('version', 'integer', ['null' => false, 'default' => 1])
            ->addColumn('etag', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['user_id'], ['name' => 'ix_notes_user_id'])
            ->addIndex(['user_id', 'created_at'], ['name' => 'ix_notes_user_created'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_notes_user_id',
            ])
            ->create();
    }

    public function down(): void
    {
        $this->table('notes')->drop()->save();
    }
}
