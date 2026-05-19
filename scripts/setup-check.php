<?php

declare(strict_types=1);

/**
 * Environment Setup Verification Script
 *
 * This script checks that all required environment variables are properly configured
 * for a fresh installation of the AI Governance Platform.
 */

echo "==================================================\n";
echo "AI Governance Platform - Setup Verification\n";
echo "==================================================\n\n";

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . '/.env';
$backendEnvFile = $projectRoot . '/backend/.env';

// Check if .env file exists
if (!file_exists($envFile)) {
    echo "❌ ERROR: .env file not found at {$envFile}\n";
    echo "   Please copy .env.example to .env and configure your settings.\n\n";
    exit(1);
}

echo "✅ Found .env file\n";

// Load environment variables
$env = [];
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    if (str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, '"\'');
}

// Required environment variables
$required = [
    'APP_NAME' => 'Application name',
    'APP_ENV' => 'Environment (development/staging/production)',
    'APP_URL' => 'Backend API URL',
    'FRONTEND_URL' => 'Frontend application URL',
    'DB_HOST' => 'Database host',
    'DB_DATABASE' => 'Database name',
    'DB_USERNAME' => 'Database username',
    'DB_PASSWORD' => 'Database password',
    'JWT_SECRET' => 'JWT signing secret',
    'MAIL_HOST' => 'SMTP host',
    'MAIL_FROM_ADDRESS' => 'From email address',
    'STORAGE_PATH' => 'Document storage path',
    'CORS_ALLOWED_ORIGINS' => 'CORS allowed origins',
];

$errors = [];
$warnings = [];

echo "\nChecking required environment variables...\n";
echo "-------------------------------------------\n";

foreach ($required as $key => $description) {
    if (!isset($env[$key]) || empty($env[$key])) {
        echo "❌ {$key} - Missing or empty\n";
        $errors[] = "{$key} ({$description}) is required";
        continue;
    }

    $value = $env[$key];

    // Check for placeholder values that need to be changed
    $placeholders = [
        'CHANGE_ME', 'change_me', 'example.com', 'your_', 'generate-a-',
        'GENERATE_SECURE_', 'localhost:8080', 'localhost:5173'
    ];

    $hasPlaceholder = false;
    foreach ($placeholders as $placeholder) {
        if (str_contains($value, $placeholder)) {
            $hasPlaceholder = true;
            break;
        }
    }

    if ($hasPlaceholder) {
        echo "⚠️  {$key} - Contains placeholder value\n";
        $warnings[] = "{$key} appears to contain a placeholder value: {$value}";
    } else {
        echo "✅ {$key} - Configured\n";
    }
}

// Specific validations
echo "\nPerforming specific validations...\n";
echo "-----------------------------------\n";

// JWT Secret strength
if (isset($env['JWT_SECRET'])) {
    $jwtSecret = $env['JWT_SECRET'];
    if (strlen($jwtSecret) < 32) {
        echo "❌ JWT_SECRET - Too short (minimum 32 characters)\n";
        $errors[] = "JWT_SECRET must be at least 32 characters long for security";
    } else {
        echo "✅ JWT_SECRET - Length OK\n";
    }
}

// Storage path
if (isset($env['STORAGE_PATH'])) {
    $storagePath = $env['STORAGE_PATH'];
    if (!is_dir($storagePath)) {
        echo "⚠️  STORAGE_PATH - Directory does not exist: {$storagePath}\n";
        $warnings[] = "Storage directory {$storagePath} does not exist (will be created on first use)";
    } elseif (!is_writable($storagePath)) {
        echo "❌ STORAGE_PATH - Directory not writable: {$storagePath}\n";
        $errors[] = "Storage directory {$storagePath} is not writable by the web server";
    } else {
        echo "✅ STORAGE_PATH - Directory exists and writable\n";
    }
}

// Database connection test (basic check)
if (isset($env['DB_HOST'], $env['DB_PORT'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_DATABASE'])) {
    try {
        $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        echo "✅ Database connection - OK\n";
    } catch (PDOException $e) {
        echo "❌ Database connection - Failed: " . $e->getMessage() . "\n";
        $errors[] = "Cannot connect to database: " . $e->getMessage();
    }
}

// Summary
echo "\n==================================================\n";
echo "Setup Verification Summary\n";
echo "==================================================\n";

if (empty($errors) && empty($warnings)) {
    echo "🎉 All checks passed! Your environment is properly configured.\n\n";
    echo "Next steps:\n";
    echo "1. Run: php scripts/migrate.php\n";
    echo "2. Run: php scripts/seed.php\n";
    echo "3. Run: php scripts/provision-superadmin.php --email your@email.com\n";
    exit(0);
}

if (!empty($warnings)) {
    echo "\n⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   - {$warning}\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ ERRORS:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    echo "\nPlease fix the errors above before proceeding.\n";
    exit(1);
}

echo "\n⚠️  Please review the warnings above and update your configuration as needed.\n";
exit(0);