<?php

declare(strict_types=1);

/**
 * Database Seed Runner
 *
 * Executes seed data files with dependency tracking
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;

class SeedRunner
{
    private Database $database;
    private string $seedsPath;
    private bool $verbose;

    public function __construct(Database $database, string $seedsPath, bool $verbose = true)
    {
        $this->database = $database;
        $this->seedsPath = $seedsPath;
        $this->verbose = $verbose;
    }

    public function run(?string $specificSeed = null): int
    {
        $this->output("==================================================");
        $this->output("Database Seed Runner");
        $this->output("==================================================\n");

        try {
            // Ensure seed tracking table exists
            $this->createSeedTrackingTable();

            // Get list of seed files
            $seedFiles = $this->getSeedFiles();

            if (empty($seedFiles)) {
                $this->output("No seed files found in: {$this->seedsPath}");
                return 0;
            }

            // Filter to specific seed if provided
            if ($specificSeed) {
                $seedFiles = array_filter($seedFiles, function($file) use ($specificSeed) {
                    return basename($file, '.sql') === $specificSeed;
                });

                if (empty($seedFiles)) {
                    $this->output("❌ Seed file not found: {$specificSeed}");
                    return 1;
                }
            }

            $this->output("Found " . count($seedFiles) . " seed files\n");

            // Get already applied seeds
            $appliedSeeds = $this->getAppliedSeeds();

            $applied = 0;
            $skipped = 0;

            foreach ($seedFiles as $seedFile) {
                $seedName = basename($seedFile, '.sql');

                if (in_array($seedName, $appliedSeeds) && !$specificSeed) {
                    $this->output("⏭️  Skipping {$seedName} (already applied)");
                    $skipped++;
                    continue;
                }

                $this->output("🌱 Seeding {$seedName}...");

                if ($this->applySeed($seedFile, $seedName, in_array($seedName, $appliedSeeds))) {
                    $this->output("✅ Applied {$seedName}");
                    $applied++;
                } else {
                    $this->output("❌ Failed to apply {$seedName}");
                    return 1;
                }
            }

            $this->output("\n==================================================");
            $this->output("Seed Summary:");
            $this->output("Applied: {$applied}");
            if (!$specificSeed) {
                $this->output("Skipped: {$skipped}");
            }
            $this->output("Total: " . count($seedFiles));
            $this->output("==================================================");

            return 0;

        } catch (Exception $e) {
            $this->output("❌ Seed failed: " . $e->getMessage());
            $this->output("File: " . $e->getFile() . ":" . $e->getLine());
            return 1;
        }
    }

    private function createSeedTrackingTable(): void
    {
        $this->output("Ensuring seed tracking table exists...");

        $sql = "
            CREATE TABLE IF NOT EXISTS seed_history (
                seed_name VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                execution_time_ms INT UNSIGNED NOT NULL DEFAULT 0,
                INDEX idx_applied_at (applied_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->database->query($sql);
        $this->output("✅ Seed tracking table ready\n");
    }

    private function getSeedFiles(): array
    {
        if (!is_dir($this->seedsPath)) {
            throw new Exception("Seeds directory not found: {$this->seedsPath}");
        }

        $files = glob($this->seedsPath . '/*.sql');

        if ($files === false) {
            throw new Exception("Failed to read seeds directory: {$this->seedsPath}");
        }

        // Sort by filename (numbered prefix like migrations)
        sort($files);

        // Validate seed file naming
        foreach ($files as $file) {
            $filename = basename($file);
            if (!preg_match('/^\d{3}_[a-z_]+\.sql$/', $filename)) {
                throw new Exception("Invalid seed filename: {$filename}. Expected format: 001_seed_name.sql");
            }
        }

        return $files;
    }

    private function getAppliedSeeds(): array
    {
        $stmt = $this->database->query("SELECT seed_name FROM seed_history ORDER BY applied_at");
        $result = $stmt->fetchAll();

        return array_column($result, 'seed_name');
    }

    private function applySeed(string $filePath, string $seedName, bool $isRerun = false): bool
    {
        try {
            $startTime = microtime(true);

            // Read seed file
            $sql = file_get_contents($filePath);
            if ($sql === false) {
                throw new Exception("Failed to read seed file: {$filePath}");
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

            $executionTime = (int) round((microtime(true) - $startTime) * 1000);

            // Record seed as applied (or update execution time if rerun)
            if ($isRerun) {
                $this->database->query(
                    "UPDATE seed_history SET applied_at = NOW(), execution_time_ms = ? WHERE seed_name = ?",
                    [$executionTime, $seedName]
                );
            } else {
                $this->database->query(
                    "INSERT INTO seed_history (seed_name, execution_time_ms) VALUES (?, ?)",
                    [$seedName, $executionTime]
                );
            }

            return true;

        } catch (Exception $e) {
            $this->output("Error in {$seedName}: " . $e->getMessage());
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
     * Get seed status for diagnostics
     */
    public function getStatus(): array
    {
        $seedFiles = $this->getSeedFiles();
        $appliedSeeds = $this->getAppliedSeeds();

        $status = [
            'total_seeds' => count($seedFiles),
            'applied_seeds' => count($appliedSeeds),
            'pending_seeds' => [],
            'applied_list' => $appliedSeeds,
        ];

        foreach ($seedFiles as $file) {
            $seedName = basename($file, '.sql');
            if (!in_array($seedName, $appliedSeeds)) {
                $status['pending_seeds'][] = $seedName;
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

        $seedsPath = __DIR__ . '/../backend/seeds';
        $verbose = !in_array('--quiet', $argv);

        $runner = new SeedRunner($database, $seedsPath, $verbose);

        if (in_array('--status', $argv)) {
            $status = $runner->getStatus();
            echo "Seed Status:\n";
            echo "Total: {$status['total_seeds']}\n";
            echo "Applied: {$status['applied_seeds']}\n";
            echo "Pending: " . count($status['pending_seeds']) . "\n";

            if (!empty($status['pending_seeds'])) {
                echo "\nPending seeds:\n";
                foreach ($status['pending_seeds'] as $seed) {
                    echo "  - {$seed}\n";
                }
            }

            exit(0);
        }

        // Check for specific seed to run
        $specificSeed = null;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--seed=')) {
                $specificSeed = substr($arg, 7);
                break;
            }
        }

        exit($runner->run($specificSeed));

    } catch (Exception $e) {
        echo "❌ Seed runner failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}