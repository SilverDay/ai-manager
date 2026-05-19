<?php

declare(strict_types=1);

/**
 * Production Monitoring Script
 *
 * Monitors application health and sends alerts when issues are detected
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;

class ProductionMonitor
{
    private Config $config;
    private Database $database;
    private array $checks = [];
    private bool $verbose;

    public function __construct(bool $verbose = false)
    {
        $this->config = Config::getInstance();
        $this->database = new Database($this->config);
        $this->verbose = $verbose;
    }

    public function runAllChecks(): array
    {
        $this->log("Starting health checks...");

        $checks = [
            'database' => $this->checkDatabase(),
            'disk_space' => $this->checkDiskSpace(),
            'file_permissions' => $this->checkFilePermissions(),
            'services' => $this->checkServices(),
            'ssl_certificate' => $this->checkSSLCertificate(),
            'log_files' => $this->checkLogFiles(),
            'backup_status' => $this->checkBackupStatus(),
        ];

        $this->checks = $checks;
        $this->generateReport();

        return $checks;
    }

    private function checkDatabase(): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'Database connection healthy',
            'details' => []
        ];

        try {
            $diagnostics = $this->database->getDiagnostics();

            if ($diagnostics['connection_status'] !== 'connected') {
                $result['status'] = 'critical';
                $result['message'] = 'Database connection failed';
                $result['details'] = $diagnostics;
                return $result;
            }

            // Check database size
            $stmt = $this->database->query("
                SELECT
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb,
                    COUNT(*) AS table_count
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$this->config->get('database.name')]);

            $dbStats = $stmt->fetch();
            $result['details']['size_mb'] = $dbStats['db_size_mb'];
            $result['details']['table_count'] = $dbStats['table_count'];

            // Check for long-running queries
            $stmt = $this->database->query("
                SELECT COUNT(*) as slow_queries
                FROM information_schema.processlist
                WHERE time > 30 AND command != 'Sleep'
            ");

            $slowQueries = $stmt->fetch()['slow_queries'];
            if ($slowQueries > 0) {
                $result['status'] = 'warning';
                $result['message'] = "Found {$slowQueries} slow queries";
            }

            $result['details']['slow_queries'] = $slowQueries;
            $result['details']['server_version'] = $diagnostics['server_version'];

        } catch (Exception $e) {
            $result['status'] = 'critical';
            $result['message'] = 'Database check failed: ' . $e->getMessage();
        }

        return $result;
    }

    private function checkDiskSpace(): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'Disk space sufficient',
            'details' => []
        ];

        $paths = [
            'root' => '/',
            'storage' => '/var/app/storage',
            'logs' => '/var/log/aigov',
            'backups' => '/var/backups/aigov'
        ];

        foreach ($paths as $name => $path) {
            if (!file_exists($path)) continue;

            $bytes = disk_free_bytes($path);
            $total = disk_total_space($path);
            $used_percent = (($total - $bytes) / $total) * 100;

            $result['details'][$name] = [
                'free_gb' => round($bytes / 1024 / 1024 / 1024, 2),
                'total_gb' => round($total / 1024 / 1024 / 1024, 2),
                'used_percent' => round($used_percent, 1)
            ];

            if ($used_percent > 90) {
                $result['status'] = 'critical';
                $result['message'] = "Critical disk space on {$name}: {$used_percent}% used";
            } elseif ($used_percent > 80) {
                $result['status'] = 'warning';
                $result['message'] = "Low disk space on {$name}: {$used_percent}% used";
            }
        }

        return $result;
    }

    private function checkFilePermissions(): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'File permissions correct',
            'details' => []
        ];

        $criticalPaths = [
            '/srv/vhosts/aim.silverday.de/.env' => '600',
            '/var/app/storage' => '750',
            '/var/log/aigov' => '750'
        ];

        foreach ($criticalPaths as $path => $expectedPerms) {
            if (!file_exists($path)) {
                $result['details'][$path] = 'missing';
                continue;
            }

            $actualPerms = substr(sprintf('%o', fileperms($path)), -3);
            $result['details'][$path] = $actualPerms;

            if ($actualPerms !== $expectedPerms) {
                $result['status'] = 'warning';
                $result['message'] = "Incorrect permissions on {$path}: {$actualPerms} (expected {$expectedPerms})";
            }
        }

        return $result;
    }

    private function checkServices(): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'All services running',
            'details' => []
        ];

        $services = ['nginx', 'php8.3-fpm', 'mysql', 'redis-server'];

        foreach ($services as $service) {
            $output = [];
            $returnCode = 0;
            exec("systemctl is-active {$service} 2>/dev/null", $output, $returnCode);

            $isActive = $returnCode === 0 && trim($output[0] ?? '') === 'active';
            $result['details'][$service] = $isActive ? 'active' : 'inactive';

            if (!$isActive) {
                $result['status'] = 'critical';
                $result['message'] = "Service {$service} is not running";
            }
        }

        return $result;
    }

    private function checkSSLCertificate(): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'SSL certificate valid',
            'details' => []
        ];

        $domain = 'aim.silverday.de';
        $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";

        if (!file_exists($certPath)) {
            $result['status'] = 'warning';
            $result['message'] = 'SSL certificate file not found';
            return $result;
        }

        $certData = openssl_x509_parse(file_get_contents($certPath));
        $expiryDate = $certData['validTo_time_t'];
        $daysUntilExpiry = ($expiryDate - time()) / 86400;

        $result['details']['expires'] = date('Y-m-d H:i:s', $expiryDate);
        $result['details']['days_until_expiry'] = round($daysUntilExpiry, 1);

        if ($daysUntilExpiry < 7) {
            $result['status'] = 'critical';
            $result['message'] = "SSL certificate expires in {$daysUntilExpiry} days";
        } elseif ($daysUntilExpiry < 30) {
            $result['status'] = 'warning';
            $result['message'] = "SSL certificate expires in {$daysUntilExpiry} days";
        }

        return $result;
    }

    private function checkLogFiles(): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'Log files healthy',
            'details' => []
        ];

        $logPath = '/var/log/aigov';
        if (!is_dir($logPath)) {
            $result['status'] = 'warning';
            $result['message'] = 'Log directory missing';
            return $result;
        }

        // Check for recent errors
        $errorLogs = glob("{$logPath}/*.log");
        $recentErrors = 0;

        foreach ($errorLogs as $logFile) {
            if (!is_readable($logFile)) continue;

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $recentLines = array_slice($lines, -100); // Last 100 lines

            foreach ($recentLines as $line) {
                if (strpos($line, date('Y-m-d')) !== false &&
                    (strpos($line, 'ERROR') !== false || strpos($line, 'CRITICAL') !== false)) {
                    $recentErrors++;
                }
            }
        }

        $result['details']['recent_errors'] = $recentErrors;
        $result['details']['log_files'] = count($errorLogs);

        if ($recentErrors > 10) {
            $result['status'] = 'warning';
            $result['message'] = "Found {$recentErrors} recent errors in logs";
        }

        return $result;
    }

    private function checkBackupStatus(): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'Backups current',
            'details' => []
        ];

        $backupPath = '/var/backups/aigov';
        if (!is_dir($backupPath)) {
            $result['status'] = 'warning';
            $result['message'] = 'Backup directory missing';
            return $result;
        }

        $backups = glob("{$backupPath}/20*", GLOB_ONLYDIR);
        $backupCount = count($backups);

        if ($backupCount === 0) {
            $result['status'] = 'critical';
            $result['message'] = 'No backups found';
            return $result;
        }

        // Check latest backup
        sort($backups);
        $latestBackup = end($backups);
        $backupTime = filemtime($latestBackup);
        $hoursOld = (time() - $backupTime) / 3600;

        $result['details']['backup_count'] = $backupCount;
        $result['details']['latest_backup'] = date('Y-m-d H:i:s', $backupTime);
        $result['details']['hours_old'] = round($hoursOld, 1);

        if ($hoursOld > 48) {
            $result['status'] = 'critical';
            $result['message'] = "Latest backup is {$hoursOld} hours old";
        } elseif ($hoursOld > 30) {
            $result['status'] = 'warning';
            $result['message'] = "Latest backup is {$hoursOld} hours old";
        }

        return $result;
    }

    private function generateReport(): void
    {
        $criticalCount = 0;
        $warningCount = 0;

        foreach ($this->checks as $checkName => $result) {
            $status = $result['status'];
            $message = $result['message'];

            if ($status === 'critical') {
                $criticalCount++;
                $this->log("❌ CRITICAL: {$checkName} - {$message}");
            } elseif ($status === 'warning') {
                $warningCount++;
                $this->log("⚠️  WARNING: {$checkName} - {$message}");
            } elseif ($this->verbose) {
                $this->log("✅ OK: {$checkName} - {$message}");
            }
        }

        $this->log("\nHealth Check Summary:");
        $this->log("Critical issues: {$criticalCount}");
        $this->log("Warnings: {$warningCount}");

        // Log to monitoring log
        $logEntry = [
            'timestamp' => date('c'),
            'summary' => [
                'critical' => $criticalCount,
                'warnings' => $warningCount,
                'total_checks' => count($this->checks)
            ],
            'checks' => $this->checks
        ];

        file_put_contents(
            '/var/log/aigov/monitor.log',
            json_encode($logEntry) . "\n",
            FILE_APPEND | LOCK_EX
        );

        // Send alert if critical issues found
        if ($criticalCount > 0) {
            $this->sendAlert($criticalCount, $warningCount);
        }
    }

    private function sendAlert(int $critical, int $warnings): void
    {
        // This would integrate with your alerting system (email, Slack, etc.)
        $this->log("🚨 ALERT: {$critical} critical issues detected!");

        // Example: Send email alert
        // $this->sendEmailAlert($critical, $warnings);
    }

    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
    }
}

// CLI execution
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    $verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

    $monitor = new ProductionMonitor($verbose);
    $results = $monitor->runAllChecks();

    // Exit with appropriate code
    $hascritical = false;
    foreach ($results as $result) {
        if ($result['status'] === 'critical') {
            $hasCritical = true;
            break;
        }
    }

    exit($hasCritical ? 1 : 0);
}