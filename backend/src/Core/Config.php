<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string, mixed> */
    private array $config = [];

    /** @var array<string, mixed> */
    private static array $defaults = [
        'app' => [
            'name' => 'AI Governance Platform',
            'env' => 'production',
            'debug' => false,
            'url' => 'http://localhost:8080',
            'frontend_url' => 'http://localhost:5173',
        ],
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'aigov',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'timeout' => 30,
            'retry_attempts' => 3,
        ],
        'jwt' => [
            'secret' => '',
            'access_ttl' => 900, // 15 minutes
            'refresh_ttl' => 604800, // 7 days
            'algorithm' => 'HS256',
        ],
        'mail' => [
            'host' => '',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'from_address' => '',
            'from_name' => 'AI Governance Platform',
        ],
        'storage' => [
            'path' => '/var/app/storage/documents',
            'max_upload_size_mb' => 50,
        ],
        'rate_limit' => [
            'auth' => 10,
            'api_read' => 120,
            'api_write' => 60,
            'upload' => 10,
        ],
        'cors' => [
            'allowed_origins' => ['http://localhost:5173'],
        ],
        'security' => [
            'bcrypt_cost' => 12,
            'session_secure' => false,
            'hsts_enabled' => false,
        ],
        'cron' => [
            'notification_interval' => 5,
            'retention_cleanup_enabled' => true,
        ],
        'locale' => [
            'default' => 'en',
        ],
    ];

    private function __construct()
    {
        $this->loadConfiguration();
    }

    public static function getInstance(): self
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Get configuration value using dot notation
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set configuration value using dot notation
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Get all configuration
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Load configuration from environment and defaults
     */
    private function loadConfiguration(): void
    {
        // Start with defaults
        $this->config = self::$defaults;

        // Load .env file if it exists
        $this->loadEnvFile();

        // Override with environment variables
        $this->loadFromEnvironment();

        // Validate critical configuration
        $this->validateConfiguration();
    }

    /**
     * Load .env file
     */
    private function loadEnvFile(): void
    {
        $envPaths = [
            dirname(__DIR__, 2) . '/.env', // backend/.env
            dirname(__DIR__, 3) . '/.env', // project root/.env
        ];

        foreach ($envPaths as $envPath) {
            if (file_exists($envPath)) {
                $this->parseEnvFile($envPath);
                break;
            }
        }
    }

    /**
     * Parse .env file
     */
    private function parseEnvFile(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');

            // Set in $_ENV for consistency
            $_ENV[$key] = $value;
        }
    }

    /**
     * Load configuration from environment variables
     */
    private function loadFromEnvironment(): void
    {
        // App configuration
        $this->setFromEnv('APP_NAME', 'app.name');
        $this->setFromEnv('APP_ENV', 'app.env');
        $this->setFromEnv('APP_DEBUG', 'app.debug', 'bool');
        $this->setFromEnv('APP_URL', 'app.url');
        $this->setFromEnv('FRONTEND_URL', 'app.frontend_url');

        // Database configuration
        $this->setFromEnv('DB_HOST', 'database.host');
        $this->setFromEnv('DB_PORT', 'database.port', 'int');
        $this->setFromEnv('DB_DATABASE', 'database.name');
        $this->setFromEnv('DB_USERNAME', 'database.username');
        $this->setFromEnv('DB_PASSWORD', 'database.password');
        $this->setFromEnv('DB_CHARSET', 'database.charset');

        // JWT configuration
        $this->setFromEnv('JWT_SECRET', 'jwt.secret');
        $this->setFromEnv('JWT_ACCESS_TTL', 'jwt.access_ttl', 'int');
        $this->setFromEnv('JWT_REFRESH_TTL', 'jwt.refresh_ttl', 'int');

        // Mail configuration
        $this->setFromEnv('MAIL_HOST', 'mail.host');
        $this->setFromEnv('MAIL_PORT', 'mail.port', 'int');
        $this->setFromEnv('MAIL_USERNAME', 'mail.username');
        $this->setFromEnv('MAIL_PASSWORD', 'mail.password');
        $this->setFromEnv('MAIL_ENCRYPTION', 'mail.encryption');
        $this->setFromEnv('MAIL_FROM_ADDRESS', 'mail.from_address');
        $this->setFromEnv('MAIL_FROM_NAME', 'mail.from_name');

        // Storage configuration
        $this->setFromEnv('STORAGE_PATH', 'storage.path');
        $this->setFromEnv('MAX_UPLOAD_SIZE_MB', 'storage.max_upload_size_mb', 'int');

        // Rate limiting
        $this->setFromEnv('RATE_LIMIT_AUTH', 'rate_limit.auth', 'int');
        $this->setFromEnv('RATE_LIMIT_API_READ', 'rate_limit.api_read', 'int');
        $this->setFromEnv('RATE_LIMIT_API_WRITE', 'rate_limit.api_write', 'int');
        $this->setFromEnv('RATE_LIMIT_UPLOAD', 'rate_limit.upload', 'int');

        // CORS
        $corsOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '';
        if ($corsOrigins) {
            $this->set('cors.allowed_origins', explode(',', $corsOrigins));
        }

        // Security
        $this->setFromEnv('BCRYPT_COST', 'security.bcrypt_cost', 'int');
        $this->setFromEnv('SESSION_SECURE', 'security.session_secure', 'bool');
        $this->setFromEnv('HSTS_ENABLED', 'security.hsts_enabled', 'bool');

        // Locale
        $this->setFromEnv('DEFAULT_LOCALE', 'locale.default');
    }

    /**
     * Set configuration value from environment variable
     */
    private function setFromEnv(string $envKey, string $configKey, string $type = 'string'): void
    {
        $value = $_ENV[$envKey] ?? null;

        if ($value === null) {
            return;
        }

        $value = match ($type) {
            'bool' => in_array(strtolower($value), ['true', '1', 'yes', 'on']),
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };

        $this->set($configKey, $value);
    }

    /**
     * Validate critical configuration values
     */
    private function validateConfiguration(): void
    {
        $required = [
            'database.host',
            'database.name',
            'database.username',
            'jwt.secret',
        ];

        $missing = [];
        foreach ($required as $key) {
            $value = $this->get($key);
            if (empty($value)) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Missing required configuration: ' . implode(', ', $missing)
            );
        }

        // Validate JWT secret strength
        $jwtSecret = $this->get('jwt.secret');
        if (strlen($jwtSecret) < 32) {
            throw new \RuntimeException(
                'JWT secret must be at least 32 characters long for security'
            );
        }
    }
}