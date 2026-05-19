<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Config;
use App\Core\Database;
use Exception;

/**
 * Production Monitoring and Health Check Controller
 */
final class MonitorController
{
    private Config $config;
    private Database $database;

    public function __construct(Config $config, Database $database)
    {
        $this->config = $config;
        $this->database = $database;
    }

    /**
     * Basic health check endpoint
     * GET /health
     */
    public function health(Request $request): Response
    {
        $health = [
            'status' => 'ok',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'environment' => $this->config->get('app.env', 'unknown')
        ];

        // Quick database check
        try {
            $this->database->query('SELECT 1');
            $health['database'] = 'ok';
        } catch (Exception $e) {
            $health['status'] = 'degraded';
            $health['database'] = 'error';
        }

        // Return appropriate status code
        if ($health['status'] === 'ok') {
            return Response::success($health);
        } else {
            // Create degraded response with 503 status
            $envelope = [
                'success' => true,
                'data' => $health,
                'errors' => [],
                'meta' => []
            ];
            return new Response($envelope, 503);
        }
    }

    /**
     * Detailed system status
     * GET /status
     */
    public function status(Request $request): Response
    {
        // Only allow in development or with special header
        $monitorKey = $request->getHeader('X-Monitor-Key');
        if ($this->config->get('app.env') === 'production' &&
            (empty($monitorKey) || $monitorKey !== $this->config->get('monitoring.secret_key'))) {
            return Response::error(['message' => 'Access denied'], 403);
        }

        $status = [
            'application' => $this->getApplicationStatus(),
            'database' => $this->getDatabaseStatus(),
            'system' => $this->getSystemStatus(),
            'security' => $this->getSecurityStatus(),
            'performance' => $this->getPerformanceMetrics()
        ];

        // Determine overall status
        $overallStatus = 'ok';
        foreach ($status as $details) {
            if ($details['status'] === 'critical') {
                $overallStatus = 'critical';
                break;
            } elseif ($details['status'] === 'warning' && $overallStatus === 'ok') {
                $overallStatus = 'warning';
            }
        }

        $response = [
            'overall_status' => $overallStatus,
            'timestamp' => date('c'),
            'server' => gethostname(),
            'checks' => $status
        ];

        // Return appropriate response
        if ($overallStatus === 'critical') {
            $envelope = [
                'success' => true,
                'data' => $response,
                'errors' => [],
                'meta' => []
            ];
            return new Response($envelope, 503);
        }

        return Response::success($response);
    }

    /**
     * Application-specific metrics
     * GET /metrics
     */
    public function metrics(Request $request): Response
    {
        $monitorKey = $request->getHeader('X-Monitor-Key');
        if ($this->config->get('app.env') === 'production' && empty($monitorKey)) {
            return Response::error(['message' => 'Access denied'], 403);
        }

        $metrics = [
            'application' => [
                'uptime_seconds' => $this->getApplicationUptime(),
                'php_version' => PHP_VERSION,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
            ],
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'requests' => $this->getRequestMetrics()
        ];

        return Response::success($metrics);
    }

    /**
     * Security monitoring endpoint
     * GET /security-status
     */
    public function securityStatus(Request $request): Response
    {
        if ($this->config->get('app.env') === 'production') {
            return Response::error(['message' => 'Endpoint disabled in production'], 404);
        }

        $security = [
            'ssl' => $this->checkSSLStatus(),
            'headers' => $this->checkSecurityHeaders($request),
            'permissions' => $this->checkFilePermissions(),
            'configuration' => $this->checkSecurityConfiguration()
        ];

        return Response::success($security);
    }

    private function getApplicationStatus(): array
    {
        $status = [
            'status' => 'ok',
            'version' => '1.0.0',
            'environment' => $this->config->get('app.env'),
            'debug_mode' => $this->config->get('app.debug', false),
            'timezone' => $this->config->get('app.timezone', 'UTC')
        ];

        // Check if maintenance mode is enabled
        if ($this->config->get('maintenance.enabled', false)) {
            $status['status'] = 'maintenance';
            $status['maintenance_message'] = $this->config->get('maintenance.message', 'System maintenance in progress');
        }

        return $status;
    }

    private function getDatabaseStatus(): array
    {
        $status = [
            'status' => 'ok',
            'connection' => 'unknown'
        ];

        try {
            $diagnostics = $this->database->getDiagnostics();
            $status['connection'] = $diagnostics['connection_status'];
            $status['server_version'] = $diagnostics['server_version'] ?? 'unknown';
            $status['host'] = $diagnostics['host'];
            $status['database'] = $diagnostics['database'];

            if ($diagnostics['connection_status'] !== 'connected') {
                $status['status'] = 'critical';
                $status['error'] = $diagnostics['error_message'] ?? 'Connection failed';
            }

        } catch (Exception $e) {
            $status['status'] = 'critical';
            $status['connection'] = 'failed';
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    private function getSystemStatus(): array
    {
        $status = [
            'status' => 'ok',
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];

        // Check disk space for project directory
        $projectRoot = dirname(dirname(dirname(__DIR__)));
        $freeBytes = disk_free_space($projectRoot);
        $totalBytes = disk_total_space($projectRoot);
        $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;

        $status['disk_free_gb'] = round($freeBytes / 1024 / 1024 / 1024, 2);
        $status['disk_used_percent'] = round($usedPercent, 1);

        if ($usedPercent > 90) {
            $status['status'] = 'critical';
        } elseif ($usedPercent > 80) {
            $status['status'] = 'warning';
        }

        // Check required PHP extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'xml', 'curl'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }

        if (!empty($missingExtensions)) {
            $status['status'] = 'critical';
            $status['missing_extensions'] = $missingExtensions;
        }

        return $status;
    }

    private function getSecurityStatus(): array
    {
        $status = [
            'status' => 'ok',
            'https_only' => $this->config->get('app.url', '') === 'https://aim.silverday.de',
            'debug_disabled' => !$this->config->get('app.debug', true),
            'error_display_off' => ini_get('display_errors') === '0'
        ];

        // Check for security issues
        $issues = [];

        if ($this->config->get('app.debug', false) && $this->config->get('app.env') === 'production') {
            $issues[] = 'Debug mode enabled in production';
            $status['status'] = 'warning';
        }

        if (ini_get('display_errors') === '1') {
            $issues[] = 'PHP error display enabled';
            $status['status'] = 'warning';
        }

        if (!empty($issues)) {
            $status['issues'] = $issues;
        }

        return $status;
    }

    private function getPerformanceMetrics(): array
    {
        $metrics = [
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_limit' => ini_get('memory_limit')
        ];

        // Add basic performance indicators
        $memoryUsagePercent = (memory_get_usage(true) / $this->parseMemoryLimit()) * 100;
        $metrics['memory_usage_percent'] = round($memoryUsagePercent, 1);

        if ($memoryUsagePercent > 80) {
            $metrics['status'] = 'warning';
        } else {
            $metrics['status'] = 'ok';
        }

        return $metrics;
    }

    private function getDatabaseMetrics(): array
    {
        try {
            // Get database size
            $stmt = $this->database->query("
                SELECT
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                    COUNT(*) AS table_count
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$this->config->get('database.name')]);

            $stats = $stmt->fetch();

            return [
                'status' => 'ok',
                'size_mb' => $stats['size_mb'] ?? 0,
                'table_count' => $stats['table_count'] ?? 0
            ];

        } catch (Exception $_) {
            return [
                'status' => 'error',
                'error' => 'Unable to fetch database metrics'
            ];
        }
    }

    private function getCacheMetrics(): array
    {
        // Placeholder for cache metrics
        return [
            'status' => 'not_configured',
            'type' => 'none'
        ];
    }

    private function getRequestMetrics(): array
    {
        // Basic request metrics
        return [
            'current_time' => microtime(true),
            'server_load' => sys_getloadavg()[0] ?? 'unknown'
        ];
    }

    private function getApplicationUptime(): int
    {
        // Simple uptime calculation based on log file
        $projectRoot = dirname(dirname(dirname(__DIR__)));
        $logFile = $projectRoot . '/storage/logs/app.log';
        if (file_exists($logFile)) {
            return time() - filemtime($logFile);
        }
        return 0;
    }

    private function checkSSLStatus(): array
    {
        $domain = 'aim.silverday.de';
        $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";

        if (!file_exists($certPath)) {
            return [
                'status' => 'warning',
                'message' => 'SSL certificate file not found'
            ];
        }

        try {
            $certData = openssl_x509_parse(file_get_contents($certPath));
            $expiryDate = $certData['validTo_time_t'];
            $daysUntilExpiry = ($expiryDate - time()) / 86400;

            $status = 'ok';
            if ($daysUntilExpiry < 7) {
                $status = 'critical';
            } elseif ($daysUntilExpiry < 30) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'expires' => date('Y-m-d H:i:s', $expiryDate),
                'days_until_expiry' => round($daysUntilExpiry, 1)
            ];

        } catch (Exception $_) {
            return [
                'status' => 'error',
                'message' => 'Unable to read SSL certificate'
            ];
        }
    }

    private function checkSecurityHeaders(Request $request): array
    {
        // Check if security headers would be present
        // This is a basic check - in production, these would be set by the web server
        $expectedHeaders = [
            'Strict-Transport-Security',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Content-Security-Policy'
        ];

        $status = 'ok';
        $missing = [];

        foreach ($expectedHeaders as $header) {
            if (empty($request->getHeader($header))) {
                $missing[] = $header;
            }
        }

        if (!empty($missing)) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'missing_headers' => $missing
        ];
    }

    private function checkFilePermissions(): array
    {
        $checks = [
            '/srv/vhosts/aim.silverday.de/.env' => '600',
            '/var/app/storage' => '750'
        ];

        $status = 'ok';
        $issues = [];

        foreach ($checks as $path => $expectedPerms) {
            if (file_exists($path)) {
                $actualPerms = substr(sprintf('%o', fileperms($path)), -3);
                if ($actualPerms !== $expectedPerms) {
                    $issues[] = "{$path}: {$actualPerms} (expected {$expectedPerms})";
                    $status = 'warning';
                }
            }
        }

        return [
            'status' => $status,
            'issues' => $issues
        ];
    }

    private function checkSecurityConfiguration(): array
    {
        $checks = [
            'jwt_secret_set' => !empty($this->config->get('auth.jwt_secret')),
            'app_key_set' => !empty($this->config->get('app.key')),
            'secure_cookies' => $this->config->get('session.cookie_secure', false),
            'httponly_cookies' => $this->config->get('session.cookie_httponly', false)
        ];

        $status = 'ok';
        $failed = array_keys(array_filter($checks, fn($v) => !$v));

        if (!empty($failed)) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'checks' => $checks,
            'failed_checks' => $failed
        ];
    }

    private function parseMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $memoryLimit;
        $unit = strtoupper(substr($memoryLimit, -1));

        return match($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value
        };
    }
}