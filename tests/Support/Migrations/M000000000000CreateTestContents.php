<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Support\Migrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the testContents table for testing BridgeFormModel with AR.
 *
 * Tests DateTimeImmutable ↔ string conversion:
 * - AR has DateTimeImmutable typed properties
 * - FormModel has string properties with format in Bridge attribute
 * - Mapper handles the conversion between types
 */
final class M000000000000CreateTestContents implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%testContents}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'name' => ColumnBuilder::string(255)->notNull(),
            'email' => ColumnBuilder::string(255)->null(),
            'age' => ColumnBuilder::integer()->null(),
            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'birthdate' => ColumnBuilder::date()->null(),
            'createdAt' => ColumnBuilder::datetime()->notNull(),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%testContents}}');
    }
}
