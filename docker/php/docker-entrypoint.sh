#!/bin/bash
set -e

echo "Starting AI Governance Platform Backend..."

# Wait for database to be ready
echo "Waiting for database connection..."
until php -r "
try {
    \$pdo = new PDO('mysql:host=mariadb;port=3306;dbname=aigov', 'aigov_user', '[jxm!0O5M6f7RziQ', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    echo 'Database connection successful\n';
    exit(0);
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . '\n';
    exit(1);
}
"; do
    echo "Database not ready, waiting 5 seconds..."
    sleep 5
done

# Create storage directories
echo "Setting up storage directories..."
mkdir -p /var/app/storage/documents
mkdir -p /var/app/storage/logs
chown -R www-data:www-data /var/app/storage

# Install Composer dependencies if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Set proper permissions
echo "Setting file permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo "Backend startup complete. Starting Apache..."

# Execute the main command
exec "$@"