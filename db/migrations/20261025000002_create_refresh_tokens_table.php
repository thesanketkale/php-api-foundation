<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRefreshTokensTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('refresh_tokens', [
            'id' => false,
            'primary_key' => 'id',
            'collation' => 'utf8mb4_0900_ai_ci',
            'encoding' => 'utf8mb4',
        ]);

        $table->addColumn('id', 'string', ['limit' => 36, 'null' => false])
            ->addColumn('user_id', 'string', ['limit' => 36, 'null' => false])
            ->addColumn('token_family', 'string', ['limit' => 36, 'null' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('sequence', 'integer', ['null' => false, 'default' => 1])
            ->addColumn('is_revoked', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uq_refresh_tokens_hash'])
            ->addIndex(['user_id'], ['name' => 'ix_refresh_tokens_user_id'])
            ->addIndex(['token_family', 'is_revoked'], ['name' => 'ix_refresh_tokens_family_revoked'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_refresh_tokens_user_id',
            ])
            ->create();
    }

    public function down(): void
    {
        $this->table('refresh_tokens')->drop()->save();
    }
}
