<?php

namespace CoinRex\Database;

/**
 * Database connection manager for CoinRex.
 *
 * Provides a PDO connection with consistent configuration.
 *
 * @package CoinRex\Database
 */
class Connection
{
    /** @var \PDO|null The singleton PDO instance. */
    private static ?\PDO $instance = null;

    /**
     * Get the database connection singleton.
     *
     * @return \PDO
     */
    public static function getInstance(): \PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    /**
     * Create a new PDO connection.
     *
     * @return \PDO
     */
    private static function createConnection(): \PDO
    {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $name = defined('DB_NAME') ? DB_NAME : 'koinrex';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);

        return $pdo;
    }

    /**
     * Reset the connection singleton (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }
}
