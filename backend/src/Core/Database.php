<?php

declare(strict_types=1);

namespace App\Core;

final class Database
{
    private ?\PDO $connection = null;
    private Config $config;
    private int $retryAttempts = 0;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get database connection, creating it if necessary
     */
    public function getConnection(): \PDO
    {
        if ($this->connection === null) {
            $this->connection = $this->createConnection();
        }

        return $this->connection;
    }

    /**
     * Test database connection without storing it
     */
    public function testConnection(): bool
    {
        try {
            $pdo = $this->createConnection();
            $pdo->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            error_log("Database connection test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get connection diagnostics
     *
     * @return array<string, mixed>
     */
    public function getDiagnostics(): array
    {
        $diagnostics = [
            'host' => $this->config->get('database.host'),
            'port' => $this->config->get('database.port'),
            'database' => $this->config->get('database.name'),
            'username' => $this->config->get('database.username'),
            'charset' => $this->config->get('database.charset'),
            'connection_status' => 'not_tested',
            'error_message' => null,
            'server_info' => null,
            'server_version' => null,
        ];

        try {
            $pdo = $this->createConnection();
            $diagnostics['connection_status'] = 'connected';
            $diagnostics['server_info'] = $pdo->getAttribute(\PDO::ATTR_SERVER_INFO);
            $diagnostics['server_version'] = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\PDOException $e) {
            $diagnostics['connection_status'] = 'failed';
            $diagnostics['error_message'] = $e->getMessage();
        }

        return $diagnostics;
    }

    /**
     * Close database connection
     */
    public function close(): void
    {
        $this->connection = null;
    }

    /**
     * Create new PDO connection with error handling and retries
     */
    private function createConnection(): \PDO
    {
        $maxRetries = $this->config->get('database.retry_attempts', 3);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->createPdoConnection();
            } catch (\PDOException $e) {
                $lastException = $e;

                error_log("Database connection attempt {$attempt}/{$maxRetries} failed: " . $e->getMessage());

                // Don't retry for authentication failures
                if (str_contains($e->getMessage(), 'Access denied')) {
                    break;
                }

                // Wait before retry (exponential backoff)
                if ($attempt < $maxRetries) {
                    $waitTime = min(pow(2, $attempt - 1), 10); // Max 10 seconds
                    sleep($waitTime);
                }
            }
        }

        throw new \PDOException(
            "Failed to connect to database after {$maxRetries} attempts. Last error: " .
            ($lastException ? $lastException->getMessage() : 'Unknown error'),
            (int) ($lastException?->getCode() ?? 0)
        );
    }

    /**
     * Create the actual PDO connection
     */
    private function createPdoConnection(): \PDO
    {
        $host = $this->config->get('database.host');
        $port = $this->config->get('database.port');
        $database = $this->config->get('database.name');
        $username = $this->config->get('database.username');
        $password = $this->config->get('database.password');
        $charset = $this->config->get('database.charset');
        $collation = $this->config->get('database.collation');
        $timeout = $this->config->get('database.timeout');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_TIMEOUT => $timeout,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$collation}",
            \PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ];

        // Enable SSL if in production
        if ($this->config->get('app.env') === 'production') {
            $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        $pdo = new \PDO($dsn, $username, $password, $options);

        // Set timezone to UTC
        $pdo->exec("SET time_zone = '+00:00'");

        // Set SQL mode for strict behavior
        $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

        return $pdo;
    }

    /**
     * Execute a query with automatic retries for connection issues
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $maxRetries = 2;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $pdo = $this->getConnection();
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } catch (\PDOException $e) {
                $lastException = $e;

                // Check if it's a connection issue
                if ($this->isConnectionError($e) && $attempt < $maxRetries) {
                    error_log("Database connection lost, retrying... (attempt {$attempt}/{$maxRetries})");
                    $this->close(); // Force reconnection
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException;
    }

    /**
     * Check if the exception is due to connection issues
     */
    private function isConnectionError(\PDOException $e): bool
    {
        $connectionErrors = [
            '2006', // MySQL server has gone away
            '2013', // Lost connection to MySQL server during query
            '2055', // Lost connection to MySQL server at reading initial communication packet
        ];

        return in_array($e->errorInfo[1] ?? '', $connectionErrors);
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollback();
    }

    /**
     * Get the last inserted ID
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }
}