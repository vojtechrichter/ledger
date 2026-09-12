<?php

declare(strict_types=1);

namespace Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Assert;

final class Database
{
    private static ?Connection $connection = null;

    public static function connection(): Connection
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $url = getenv('DATABASE_URL');
        if ($url === false) {
            $env = parse_ini_file(__DIR__ . '/../../.env');
            $url = is_array($env) && is_string($env['DATABASE_URL'] ?? null) ? $env['DATABASE_URL'] : null;
        }
        if ($url === null) {
            Assert::markTestSkipped('DATABASE_URL is not set.');
        }

        $connection = DriverManager::getConnection(new DsnParser()->parse($url));
        try {
            $connection->executeQuery('SELECT 1');
        } catch (DbalException $e) {
            Assert::markTestSkipped('Postgres is unreachable: ' . $e->getMessage());
        }

        return self::$connection = $connection;
    }

    public static function truncateEventStore(): void
    {
        self::connection()->executeStatement('TRUNCATE outbox, events, snapshots RESTART IDENTITY');
    }
}
