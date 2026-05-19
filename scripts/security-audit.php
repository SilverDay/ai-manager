<?php

declare(strict_types=1);

/**
 * Security Audit Runner
 *
 * Comprehensive security scanning for the project
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

class SecurityAuditor
{
    private bool $verbose;
    private array $issues = [];

    public function __construct(bool $verbose = true)
    {
        $this->verbose = $verbose;
    }

    public function run(): int
    {
        $this->output("==================================================");
        $this->output("Security Audit Runner");
        $this->output("==================================================\n");

        $checks = [
            'checkPhpDependencies',
            'checkNodeDependencies',
            'checkConfigurationFiles',
            'checkFilePermissions',
            'checkSecretPatterns',
            'checkDatabaseCredentials',
            'checkPhpConfiguration',
        ];

        foreach ($checks as $check) {
            $this->output("Running {$check}...");
            $this->$check();
            $this->output("");
        }

        return $this->generateReport();
    }

    private function checkPhpDependencies(): void
    {
        $this->output("Checking PHP dependencies for vulnerabilities...");

        $composerPath = __DIR__ . '/../backend';
        if (!file_exists($composerPath . '/composer.lock')) {
            $this->addIssue('warning', 'Composer lock file not found', 'Run composer install to generate lock file');
            return;
        }

        $currentDir = getcwd();
        chdir($composerPath);

        // Run composer audit
        $output = [];
        $exitCode = 0;
        exec('composer audit --format=json 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->addIssue('high', 'PHP dependency vulnerabilities found', implode("\n", $output));
        } else {
            $this->output("✅ No PHP dependency vulnerabilities found");
        }

        chdir($currentDir);
    }

    private function checkNodeDependencies(): void
    {
        $this->output("Checking Node.js dependencies for vulnerabilities...");

        $frontendPath = __DIR__ . '/../frontend';
        if (!file_exists($frontendPath . '/package-lock.json')) {
            $this->addIssue('warning', 'NPM lock file not found', 'Run npm install to generate lock file');
            return;
        }

        $currentDir = getcwd();
        chdir($frontendPath);

        // Run npm audit
        $output = [];
        $exitCode = 0;
        exec('npm audit --audit-level=moderate --production --parseable 2>&1', $output, $exitCode);

        if ($exitCode !== 0 && !empty($output)) {
            $vulnerabilities = array_filter($output, function($line) {
                return str_contains($line, 'https://www.npmjs.com/advisories/');
            });

            if (!empty($vulnerabilities)) {
                $this->addIssue('high', 'Node.js dependency vulnerabilities found', implode("\n", $vulnerabilities));
            }
        } else {
            $this->output("✅ No Node.js dependency vulnerabilities found");
        }

        chdir($currentDir);
    }

    private function checkConfigurationFiles(): void
    {
        $this->output("Checking configuration files for security issues...");

        $projectRoot = __DIR__ . '/..';

        // Check for .env files in wrong locations
        $badEnvLocations = [
            $projectRoot . '/frontend/.env',
            $projectRoot . '/backend/public/.env',
            $projectRoot . '/backend/src/.env',
        ];

        foreach ($badEnvLocations as $location) {
            if (file_exists($location)) {
                $this->addIssue('critical', 'Environment file in unsafe location', "Found .env at: {$location}");
            }
        }

        // Check .env.example exists
        if (!file_exists($projectRoot . '/.env.example')) {
            $this->addIssue('medium', 'Missing .env.example file', 'Create .env.example with placeholder values');
        }

        // Check .gitignore includes .env
        $gitignorePath = $projectRoot . '/.gitignore';
        if (file_exists($gitignorePath)) {
            $gitignore = file_get_contents($gitignorePath);
            if (!str_contains($gitignore, '.env')) {
                $this->addIssue('high', '.env not in .gitignore', 'Add .env to .gitignore to prevent secret exposure');
            }
        } else {
            $this->addIssue('medium', 'Missing .gitignore file', 'Create .gitignore to prevent accidental commits');
        }

        $this->output("✅ Configuration files checked");
    }

    private function checkFilePermissions(): void
    {
        $this->output("Checking file permissions...");

        $projectRoot = __DIR__ . '/..';

        // Check for executable PHP files (security risk)
        $executablePhp = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot . '/backend/src')
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                if (is_executable($file->getPathname())) {
                    $executablePhp[] = $file->getPathname();
                }
            }
        }

        if (!empty($executablePhp)) {
            $this->addIssue('medium', 'Executable PHP files found', implode("\n", $executablePhp));
        }

        // Check for world-writable files
        $writableFiles = [];
        $finder = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($finder as $file) {
            if ($file->isFile()) {
                $perms = fileperms($file->getPathname());
                if (($perms & 0002) || ($perms & 0020)) { // World or group writable
                    $writableFiles[] = $file->getPathname();
                }
            }
        }

        if (!empty($writableFiles)) {
            $count = min(count($writableFiles), 10); // Show max 10 files
            $this->addIssue('low', 'World/group writable files found',
                implode("\n", array_slice($writableFiles, 0, $count)) .
                (count($writableFiles) > 10 ? "\n... and " . (count($writableFiles) - 10) . " more" : ""));
        }

        $this->output("✅ File permissions checked");
    }

    private function checkSecretPatterns(): void
    {
        $this->output("Scanning for potential hardcoded secrets...");

        $projectRoot = __DIR__ . '/..';
        $suspiciousPatterns = [
            '/password\s*=\s*["\'][^"\']{8,}["\']/i',
            '/api[_-]?key\s*[=:]\s*["\'][^"\']{16,}["\']/i',
            '/secret\s*[=:]\s*["\'][^"\']{16,}["\']/i',
            '/token\s*[=:]\s*["\'][^"\']{16,}["\']/i',
            '/private[_-]?key\s*[=:]\s*["\'][^"\']{32,}["\']/i',
        ];

        $suspiciousFiles = [];
        $scanDirs = ['backend/src', 'frontend/src', 'scripts'];

        foreach ($scanDirs as $dir) {
            $fullDir = $projectRoot . '/' . $dir;
            if (!is_dir($fullDir)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullDir)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;

                $extension = strtolower($file->getExtension());
                if (!in_array($extension, ['php', 'js', 'vue', 'ts', 'json', 'env'])) continue;

                $content = file_get_contents($file->getPathname());
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $content, $matches)) {
                        $suspiciousFiles[] = [
                            'file' => $file->getPathname(),
                            'pattern' => $pattern,
                            'match' => $matches[0]
                        ];
                    }
                }
            }
        }

        if (!empty($suspiciousFiles)) {
            $details = array_map(function($item) {
                return "{$item['file']}: {$item['match']}";
            }, array_slice($suspiciousFiles, 0, 5)); // Show first 5

            $this->addIssue('high', 'Potential hardcoded secrets found', implode("\n", $details));
        }

        $this->output("✅ Secret pattern scan completed");
    }

    private function checkDatabaseCredentials(): void
    {
        $this->output("Checking database configuration security...");

        $envPath = __DIR__ . '/../.env';
        if (!file_exists($envPath)) {
            $this->addIssue('warning', 'Environment file not found', 'Database configuration cannot be checked');
            return;
        }

        $envContent = file_get_contents($envPath);

        // Check for weak database passwords
        if (preg_match('/DB_PASSWORD\s*=\s*(.*)/', $envContent, $matches)) {
            $password = trim($matches[1], '"\'');

            if (empty($password)) {
                $this->addIssue('critical', 'Empty database password', 'Database password should not be empty');
            } elseif (strlen($password) < 12) {
                $this->addIssue('medium', 'Weak database password', 'Database password should be at least 12 characters');
            } elseif (in_array(strtolower($password), ['password', '123456', 'admin', 'root'])) {
                $this->addIssue('high', 'Common database password', 'Database password is too common/predictable');
            }
        }

        // Check for default database names
        if (preg_match('/DB_DATABASE\s*=\s*(.*)/', $envContent, $matches)) {
            $database = trim($matches[1], '"\'');
            if (in_array(strtolower($database), ['test', 'admin', 'root', 'mysql'])) {
                $this->addIssue('low', 'Common database name', 'Consider using a more specific database name');
            }
        }

        $this->output("✅ Database configuration checked");
    }

    private function checkPhpConfiguration(): void
    {
        $this->output("Checking PHP security configuration...");

        // Check for dangerous PHP settings
        $dangerousSettings = [
            'register_globals' => 'on',
            'expose_php' => 'on',
            'display_errors' => 'on',
            'log_errors' => 'off',
            'file_uploads' => 'off', // This should usually be on, but we check it
        ];

        foreach ($dangerousSettings as $setting => $dangerousValue) {
            $currentValue = ini_get($setting);
            if ($setting === 'file_uploads' && $currentValue === '1') {
                // File uploads being on is usually fine
                continue;
            }

            if ($currentValue === $dangerousValue) {
                $this->addIssue('medium', "Potentially unsafe PHP setting: {$setting}",
                    "Current value: {$currentValue}, consider changing this setting");
            }
        }

        $this->output("✅ PHP configuration checked");
    }

    private function addIssue(string $severity, string $title, string $description): void
    {
        $this->issues[] = [
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
        ];

        if ($this->verbose) {
            $icon = match($severity) {
                'critical' => '🚨',
                'high' => '⚠️',
                'medium' => '⚡',
                'low' => 'ℹ️',
                'warning' => '⚠️',
                default => '❓'
            };

            $this->output("{$icon} [{$severity}] {$title}");
            if ($this->verbose) {
                $this->output("   {$description}");
            }
        }
    }

    private function generateReport(): int
    {
        $this->output("\n==================================================");
        $this->output("Security Audit Report");
        $this->output("==================================================");

        if (empty($this->issues)) {
            $this->output("✅ No security issues found!");
            return 0;
        }

        $severityCounts = array_count_values(array_column($this->issues, 'severity'));

        $this->output("Issues found:");
        foreach (['critical', 'high', 'medium', 'low', 'warning'] as $severity) {
            $count = $severityCounts[$severity] ?? 0;
            if ($count > 0) {
                $this->output("  {$severity}: {$count}");
            }
        }

        $this->output("\nDetailed Issues:");
        foreach ($this->issues as $issue) {
            $this->output("\n[{$issue['severity']}] {$issue['title']}");
            $this->output("  {$issue['description']}");
        }

        // Return exit code based on severity
        if (isset($severityCounts['critical']) && $severityCounts['critical'] > 0) {
            return 2;
        } elseif (isset($severityCounts['high']) && $severityCounts['high'] > 0) {
            return 1;
        }

        return 0;
    }

    private function output(string $message): void
    {
        if ($this->verbose) {
            echo $message . PHP_EOL;
        }
    }
}

// Main execution
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    $verbose = !in_array('--quiet', $argv);

    $auditor = new SecurityAuditor($verbose);
    $exitCode = $auditor->run();

    exit($exitCode);
}