<?php

declare(strict_types=1);

/**
 * Database Connection Test Script
 *
 * This script tests the database connectivity and basic query functionality
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

// IDE hint: Classes are properly autoloaded at runtime
use App\Core\Config;
use App\Core\Database;

echo "==================================================\n";
echo "Database Connection Test\n";
echo "==================================================\n\n";

try {
    // Load configuration
    echo "1. Loading configuration...\n";
    $config = Config::getInstance();
    echo "   ✅ Configuration loaded successfully\n";
    echo "   Environment: " . $config->get('app.env') . "\n";
    echo "   Database: " . $config->get('database.host') . ':' . $config->get('database.port') . '/' . $config->get('database.name') . "\n\n";

    // Test database connection
    echo "2. Testing database connection...\n";
    $database = new Database($config);

    if ($database->testConnection()) {
        echo "   ✅ Database connection successful\n";
    } else {
        echo "   ❌ Database connection failed\n";
        exit(1);
    }

    // Get diagnostics
    echo "\n3. Database diagnostics:\n";
    $diagnostics = $database->getDiagnostics();
    foreach ($diagnostics as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            echo "   {$key}: {$value}\n";
        }
    }

    // Test a simple query
    echo "\n4. Testing simple query...\n";
    $stmt = $database->query('SELECT 1 AS test_value');
    $result = $stmt->fetch();
    echo "   ✅ Basic query executed successfully\n";
    echo "   Test value: " . $result['test_value'] . "\n";

    // Test parameterized query
    echo "\n5. Testing parameterized query...\n";
    $stmt = $database->query('SELECT ? AS param_value', ['test_parameter']);
    $result = $stmt->fetch();
    echo "   ✅ Parameterized query executed successfully\n";
    echo "   Parameter value: " . $result['param_value'] . "\n";

    // Test transaction support
    echo "\n6. Testing transaction support...\n";
    $database->beginTransaction();
    echo "   ✅ Transaction started\n";
    $database->rollback();
    echo "   ✅ Transaction rolled back\n";

    echo "\n==================================================\n";
    echo "✅ All database tests passed successfully!\n";
    echo "==================================================\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}