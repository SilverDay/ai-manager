<?php

declare(strict_types=1);

/**
 * Database Migration Runner
 *
 * Executes numbered SQL migration files and tracks applied migrations
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;

class MigrationRunner
{
    private Database $database;
    private string $migrationsPath;
    private bool $verbose;

    public function __construct(Database $database, string $migrationsPath, bool $verbose = true)
    {
        $this->database = $database;
        $this->migrationsPath = $migrationsPath;
        $this->verbose = $verbose;
    }

    public function run(): int
    {
        $this->output("==================================================");
        $this->output("Database Migration Runner");
        $this->output("==================================================\n");

        try {
            // Ensure migration tracking table exists
            $this->createMigrationTrackingTable();

            // Get list of migration files
            $migrationFiles = $this->getMigrationFiles();

            if (empty($migrationFiles)) {
                $this->output("No migration files found in: {$this->migrationsPath}");
                return 0;
            }

            $this->output("Found " . count($migrationFiles) . " migration files\n");

            // Get already applied migrations
            $appliedMigrations = $this->getAppliedMigrations();

            $applied = 0;
            $skipped = 0;

            foreach ($migrationFiles as $migrationFile) {
                $migrationName = basename($migrationFile, '.sql');

                if (in_array($migrationName, $appliedMigrations)) {
                    $this->output("⏭️  Skipping {$migrationName} (already applied)");
                    $skipped++;
                    continue;
                }

                $this->output("🔄 Applying {$migrationName}...");

                if ($this->applyMigration($migrationFile, $migrationName)) {
                    $this->output("✅ Applied {$migrationName}");
                    $applied++;
                } else {
                    $this->output("❌ Failed to apply {$migrationName}");
                    return 1;
                }
            }

            $this->output("\n==================================================");
            $this->output("Migration Summary:");
            $this->output("Applied: {$applied}");
            $this->output("Skipped: {$skipped}");
            $this->output("Total: " . count($migrationFiles));
            $this->output("==================================================");

            return 0;

        } catch (Exception $e) {
            $this->output("❌ Migration failed: " . $e->getMessage());
            $this->output("File: " . $e->getFile() . ":" . $e->getLine());
            return 1;
        }
    }

    private function createMigrationTrackingTable(): void
    {
        $this->output("Ensuring migration tracking table exists...");

        $sql = "
            CREATE TABLE IF NOT EXISTS schema_migrations (
                migration_name VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_applied_at (applied_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->database->query($sql);
        $this->output("✅ Migration tracking table ready\n");
    }

    private function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            throw new Exception("Migration directory not found: {$this->migrationsPath}");
        }

        $files = glob($this->migrationsPath . '/*.sql');

        if ($files === false) {
            throw new Exception("Failed to read migration directory: {$this->migrationsPath}");
        }

        // Sort by filename (which should be numbered)
        sort($files);

        // Validate migration file naming
        foreach ($files as $file) {
            $filename = basename($file);
            if (!preg_match('/^\d{3}_[a-z_]+\.sql$/', $filename)) {
                throw new Exception("Invalid migration filename: {$filename}. Expected format: 001_description.sql");
            }
        }

        return $files;
    }

    private function getAppliedMigrations(): array
    {
        $stmt = $this->database->query("SELECT migration_name FROM schema_migrations ORDER BY applied_at");
        $result = $stmt->fetchAll();

        return array_column($result, 'migration_name');
    }

    private function applyMigration(string $filePath, string $migrationName): bool
    {
        try {
            // Read migration file
            $sql = file_get_contents($filePath);
            if ($sql === false) {
                throw new Exception("Failed to read migration file: {$filePath}");
            }

            // Split SQL by semicolons and execute each statement
            $statements = $this->splitSqlStatements($sql);

            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if (empty($trimmed) || str_starts_with($trimmed, '--')) {
                    continue; // Skip empty lines and comments
                }

                $this->database->query($trimmed);
            }

            // Record migration as applied (DDL statements auto-commit, so this is safe)
            $this->database->query(
                "INSERT INTO schema_migrations (migration_name) VALUES (?)",
                [$migrationName]
            );

            return true;

        } catch (Exception $e) {
            $this->output("Error in {$migrationName}: " . $e->getMessage());
            return false;
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);

        // Split by semicolon, but be careful of semicolons in strings
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = null;

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar) {
                $inString = false;
                $stringChar = null;
            } elseif (!$inString && $char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (!empty(trim($current))) {
            $statements[] = $current;
        }

        return array_filter(array_map('trim', $statements));
    }

    private function output(string $message): void
    {
        if ($this->verbose) {
            echo $message . PHP_EOL;
        }
    }

    /**
     * Get migration status for diagnostics
     */
    public function getStatus(): array
    {
        $migrationFiles = $this->getMigrationFiles();
        $appliedMigrations = $this->getAppliedMigrations();

        $status = [
            'total_migrations' => count($migrationFiles),
            'applied_migrations' => count($appliedMigrations),
            'pending_migrations' => [],
            'applied_list' => $appliedMigrations,
        ];

        foreach ($migrationFiles as $file) {
            $migrationName = basename($file, '.sql');
            if (!in_array($migrationName, $appliedMigrations)) {
                $status['pending_migrations'][] = $migrationName;
            }
        }

        return $status;
    }
}

// Main execution
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    try {
        $config = Config::getInstance();
        $database = new Database($config);

        $migrationsPath = __DIR__ . '/../backend/migrations';
        $verbose = !in_array('--quiet', $argv);

        $runner = new MigrationRunner($database, $migrationsPath, $verbose);

        if (in_array('--status', $argv)) {
            $status = $runner->getStatus();
            echo "Migration Status:\n";
            echo "Total: {$status['total_migrations']}\n";
            echo "Applied: {$status['applied_migrations']}\n";
            echo "Pending: " . count($status['pending_migrations']) . "\n";

            if (!empty($status['pending_migrations'])) {
                echo "\nPending migrations:\n";
                foreach ($status['pending_migrations'] as $migration) {
                    echo "  - {$migration}\n";
                }
            }

            exit(0);
        }

        exit($runner->run());

    } catch (Exception $e) {
        echo "❌ Migration runner failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}