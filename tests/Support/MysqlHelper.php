<?php

declare(strict_types=1);

/**
 * MysqlHelper.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel\Tests\Support;

use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

final class MysqlHelper
{
    public function createConnection(): ConnectionInterface
    {
        $driver = $_ENV['DB_DRIVER'] ?? 'mysql';
        $database = $_ENV['DB_DATABASE'] ?? '';
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $user = $_ENV['DB_USER'] ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        $pdoDriver = new Driver("$driver:host=$host;dbname=$database;port=$port", $user, $password);
        $pdoDriver->charset('UTF8MB4');

        return new Connection($pdoDriver, new SchemaCache(new MemorySimpleCache()));
    }
}
