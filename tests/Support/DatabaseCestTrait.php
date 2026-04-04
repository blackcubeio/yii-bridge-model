<?php

declare(strict_types=1);

/**
 * DatabaseCestTrait.php
 *
 * PHP version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel\Tests\Support;

use Blackcube\BridgeModel\Tests\Support\Migrations\M000000000000CreateTestContents;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

/**
 * Trait for Cest classes that need database setup.
 *
 * Lifecycle: drop + create tables before each test (tests rely on predictable auto-increment IDs).
 */
trait DatabaseCestTrait
{
    protected ConnectionInterface $db;

    public function _before(IntegrationTester $I): void
    {
        $this->initializeDatabase();
        $this->createTables();
    }

    private function initializeDatabase(): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();
        ConnectionProvider::set($this->db);
    }

    private function createTables(): void
    {
        $this->db->createCommand('DROP TABLE IF EXISTS `testContents`')->execute();

        $migration = new M000000000000CreateTestContents();
        $builder = new MigrationBuilder($this->db, new NullMigrationInformer());
        $migration->up($builder);
    }
}
