<?php

declare(strict_types=1);

/**
 * Provision Initial Superadmin User
 *
 * Creates the first superadmin user for the platform.
 * Superadmins have tenant_id = NULL and can manage all tenants.
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;

function showUsage(): void
{
    echo "Usage: php provision-superadmin.php [options]\n\n";
    echo "Options:\n";
    echo "  --email <email>      Superadmin email address (required)\n";
    echo "  --password <pass>    Password (optional, will prompt if not provided)\n";
    echo "  --first-name <name>  First name (optional, defaults to 'Super')\n";
    echo "  --last-name <name>   Last name (optional, defaults to 'Admin')\n";
    echo "  --force              Overwrite existing superadmin with same email\n";
    echo "  --help               Show this help message\n\n";
    echo "Example:\n";
    echo "  php provision-superadmin.php --email admin@example.com --first-name Klaus --last-name Klingner\n\n";
}

function parseArguments(array $argv): array
{
    $args = [
        'email' => null,
        'password' => null,
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'force' => false,
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        switch ($argv[$i]) {
            case '--email':
                $args['email'] = $argv[++$i] ?? null;
                break;
            case '--password':
                $args['password'] = $argv[++$i] ?? null;
                break;
            case '--first-name':
                $args['first_name'] = $argv[++$i] ?? null;
                break;
            case '--last-name':
                $args['last_name'] = $argv[++$i] ?? null;
                break;
            case '--force':
                $args['force'] = true;
                break;
            case '--help':
                $args['help'] = true;
                break;
            default:
                echo "Unknown option: {$argv[$i]}\n\n";
                showUsage();
                exit(1);
        }
    }

    return $args;
}

function promptPassword(): string
{
    echo "Enter password for superadmin (min. 12 characters): ";

    // Hide password input
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        $password = shell_exec('powershell -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"');
    } else {
        // Unix/Linux
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
    }

    echo "\n";
    return $password;
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword(string $password): array
{
    $errors = [];

    if (strlen($password) < 12) {
        $errors[] = "Password must be at least 12 characters long";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }

    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    return $errors;
}

function createSuperadmin(Database $db, array $userData): bool
{
    $pdo = $db->getConnection();

    try {
        $pdo->beginTransaction();

        // Check if superadmin with this email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id IS NULL");
        $stmt->execute([$userData['email']]);
        $existingUser = $stmt->fetch();

        if ($existingUser && !$userData['force']) {
            echo "❌ Superadmin with email {$userData['email']} already exists.\n";
            echo "   Use --force to overwrite existing superadmin.\n";
            return false;
        }

        if ($existingUser && $userData['force']) {
            echo "🔄 Overwriting existing superadmin...\n";
            $stmt = $pdo->prepare("DELETE FROM users WHERE email = ? AND tenant_id IS NULL");
            $stmt->execute([$userData['email']]);
        }

        // Get the Superadmin role ID
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Superadmin'");
        $stmt->execute();
        $superadminRole = $stmt->fetch();

        if (!$superadminRole) {
            echo "❌ Superadmin role not found. Please run seed data first.\n";
            return false;
        }

        // Create the superadmin user
        $passwordHash = password_hash($userData['password'], PASSWORD_ARGON2ID);

        $stmt = $pdo->prepare("
            INSERT INTO users (
                tenant_id, role_id, first_name, last_name, email, password_hash,
                email_verified_at, account_status, created_at
            ) VALUES (
                NULL, ?, ?, ?, ?, ?, NOW(), 'active', NOW()
            )
        ");

        $stmt->execute([
            $superadminRole['id'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['email'],
            $passwordHash
        ]);

        $userId = $pdo->lastInsertId();

        $pdo->commit();

        echo "✅ Superadmin user created successfully!\n";
        echo "   Email: {$userData['email']}\n";
        echo "   Name: {$userData['first_name']} {$userData['last_name']}\n";
        echo "   User ID: {$userId}\n\n";
        echo "🔐 You can now log in at: https://aim.silverday.de/login\n";

        return true;

    } catch (Exception $e) {
        $pdo->rollback();
        echo "❌ Failed to create superadmin: " . $e->getMessage() . "\n";
        return false;
    }
}

// Main execution
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    try {
        $args = parseArguments($argv);

        if ($args['help']) {
            showUsage();
            exit(0);
        }

        if (!$args['email']) {
            echo "❌ Email address is required.\n\n";
            showUsage();
            exit(1);
        }

        if (!validateEmail($args['email'])) {
            echo "❌ Invalid email address format.\n";
            exit(1);
        }

        if (!$args['password']) {
            $args['password'] = promptPassword();
        }

        $passwordErrors = validatePassword($args['password']);
        if (!empty($passwordErrors)) {
            echo "❌ Password validation failed:\n";
            foreach ($passwordErrors as $error) {
                echo "   - {$error}\n";
            }
            exit(1);
        }

        echo "==================================================\n";
        echo "Provisioning Superadmin User\n";
        echo "==================================================\n\n";

        $config = Config::getInstance();
        $database = new Database($config);

        echo "📋 Creating superadmin with:\n";
        echo "   Email: {$args['email']}\n";
        echo "   Name: {$args['first_name']} {$args['last_name']}\n";
        echo "   Force: " . ($args['force'] ? 'Yes' : 'No') . "\n\n";

        if (createSuperadmin($database, $args)) {
            exit(0);
        } else {
            exit(1);
        }

    } catch (Exception $e) {
        echo "❌ Provisioning failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}