<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('users', [
            'id' => false,
            'primary_key' => 'id',
            'collation' => 'utf8mb4_0900_ai_ci',
            'encoding' => 'utf8mb4',
        ]);

        $table->addColumn('id', 'string', ['limit' => 36, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['email'], ['unique' => true, 'name' => 'uq_users_email'])
            ->create();
    }

    public function down(): void
    {
        $this->table('users')->drop()->save();
    }
}
