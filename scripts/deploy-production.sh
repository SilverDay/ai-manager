#!/bin/bash

set -euo pipefail

# Production Deployment Script for AI Governance Platform
# Usage: ./scripts/deploy-production.sh [--skip-backup] [--skip-tests]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
DEPLOY_USER="www-data"
DEPLOY_GROUP="www-data"
BACKUP_DIR="${PROJECT_ROOT}/storage/backups"
LOG_FILE="${PROJECT_ROOT}/storage/logs/deploy.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    local level=$1
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    echo -e "${timestamp} [${level}] ${message}" | tee -a "${LOG_FILE}"

    case $level in
        "ERROR") echo -e "${RED}ERROR: ${message}${NC}" >&2 ;;
        "WARN")  echo -e "${YELLOW}WARN:  ${message}${NC}" ;;
        "INFO")  echo -e "${BLUE}INFO:  ${message}${NC}" ;;
        "SUCCESS") echo -e "${GREEN}SUCCESS: ${message}${NC}" ;;
    esac
}

# Error handler
error_handler() {
    local line_number=$1
    log "ERROR" "Deployment failed at line ${line_number}. Check logs for details."
    exit 1
}

trap 'error_handler ${LINENO}' ERR

# Check if we can write to project directory
check_permissions() {
    if [[ ! -w "${PROJECT_ROOT}" ]]; then
        log "ERROR" "Cannot write to project directory: ${PROJECT_ROOT}"
        log "ERROR" "Please ensure you have write permissions to this directory"
        exit 1
    fi

    log "INFO" "Running deployment in user mode (no system-wide changes)"
}

# Create necessary directories within project
create_directories() {
    log "INFO" "Creating necessary directories within project..."

    mkdir -p "${PROJECT_ROOT}/storage/logs"
    mkdir -p "${PROJECT_ROOT}/storage/documents"
    mkdir -p "${PROJECT_ROOT}/storage/backups"
    mkdir -p "${PROJECT_ROOT}/storage/cache"
    mkdir -p "${PROJECT_ROOT}/config/production"

    # Set proper ownership (if running as root, otherwise skip)
    if [[ $EUID -eq 0 ]]; then
        chown -R ${DEPLOY_USER}:${DEPLOY_GROUP} "${PROJECT_ROOT}/storage"
        chmod 750 "${PROJECT_ROOT}/storage"
        chmod 700 "${PROJECT_ROOT}/storage/backups"
    else
        chmod 750 "${PROJECT_ROOT}/storage"
        chmod 700 "${PROJECT_ROOT}/storage/backups"
    fi

    log "SUCCESS" "Project directories created successfully"
}

# Check system dependencies (don't install)
check_system_dependencies() {
    log "INFO" "Checking system dependencies..."

    local missing_deps=()

    # Check PHP 8.3+
    if ! command -v php &> /dev/null; then
        missing_deps+=("php-cli")
    else
        local php_version=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
        if [[ $(echo "$php_version >= 8.3" | bc -l) -eq 0 ]]; then
            missing_deps+=("php-8.3+")
        fi
    fi

    # Check Composer
    if ! command -v composer &> /dev/null; then
        missing_deps+=("composer")
    fi

    # Check Node.js 18+
    if ! command -v node &> /dev/null; then
        missing_deps+=("nodejs")
    else
        local node_version=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
        if [[ $node_version -lt 18 ]]; then
            missing_deps+=("nodejs-18+")
        fi
    fi

    # Check required PHP extensions
    local required_extensions=("pdo_mysql" "mbstring" "xml" "curl" "zip" "intl" "json")
    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "$ext"; then
            missing_deps+=("php-$ext")
        fi
    done

    if [[ ${#missing_deps[@]} -gt 0 ]]; then
        log "ERROR" "Missing dependencies: ${missing_deps[*]}"
        log "ERROR" "Please install the missing dependencies and run this script again"
        log "INFO" "For Ubuntu/Debian:"
        log "INFO" "  sudo apt install php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl composer nodejs npm"
        return 1
    fi

    log "SUCCESS" "All dependencies available"
}

# Generate PHP configuration template
generate_php_config() {
    log "INFO" "Generating PHP configuration template..."

    # Create PHP-FPM pool configuration template
    cat > "${PROJECT_ROOT}/config/php-fpm-pool.conf" <<EOF
; PHP-FPM pool configuration for AI Governance Platform
; Copy this to your PHP-FPM pool directory (e.g., /etc/php/8.3/fpm/pool.d/aigov.conf)

[aigov]
user = www-data
group = www-data
listen = /run/php/php8.3-fpm-aigov.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10
pm.max_requests = 500

; Security settings
php_admin_value[expose_php] = off
php_admin_value[display_errors] = off
php_admin_value[log_errors] = on
php_admin_value[error_log] = ${PROJECT_ROOT}/storage/logs/php-error.log
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 60
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
php_admin_value[max_file_uploads] = 10

; Session security
php_admin_value[session.cookie_httponly] = 1
php_admin_value[session.cookie_secure] = 1
php_admin_value[session.use_strict_mode] = 1
php_admin_value[session.cookie_samesite] = "Strict"
EOF

    log "SUCCESS" "PHP configuration template created at config/php-fpm-pool.conf"
}

# Generate Nginx configuration template
generate_nginx_config() {
    log "INFO" "Generating Nginx configuration template..."

    # Create Nginx configuration template
    cat > "${PROJECT_ROOT}/config/nginx-site.conf" <<EOF
# Rate limiting zones
limit_req_zone \$binary_remote_addr zone=api:10m rate=60r/m;
limit_req_zone \$binary_remote_addr zone=auth:10m rate=10r/m;
limit_req_zone \$binary_remote_addr zone=upload:10m rate=5r/m;

server {
    listen 80;
    server_name aim.silverday.de;

    # Redirect to HTTPS
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name aim.silverday.de;

    # Document root points to backend public directory
    root ${PROJECT_ROOT}/backend/public;
    index index.php;

    # SSL Configuration (certbot will modify this)
    ssl_certificate /etc/letsencrypt/live/aim.silverday.de/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/aim.silverday.de/privkey.pem;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'" always;

    # Hide Nginx version
    server_tokens off;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;

    # API routes with rate limiting
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Auth routes with stricter rate limiting
    location /api/v1/auth/ {
        limit_req zone=auth burst=5 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Upload routes with strict rate limiting
    location /api/v1/documents/upload {
        limit_req zone=upload burst=2 nodelay;
        client_max_body_size 50M;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Serve built frontend files
    location / {
        try_files \$uri \$uri/ @frontend;
    }

    # Frontend fallback
    location @frontend {
        root ${PROJECT_ROOT}/frontend/dist;
        try_files \$uri \$uri/ /index.html;
    }

    # Frontend static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        root ${PROJECT_ROOT}/frontend/dist;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # PHP handler
    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php8.3-fpm-aigov.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;

        # Security
        fastcgi_param HTTP_PROXY "";
        fastcgi_hide_header X-Powered-By;
    }

    # Security: Deny access to sensitive files
    location ~ /\.(env|git|claude) {
        deny all;
    }

    location ~ /(vendor|node_modules|storage|scripts|backend/(src|tests|migrations|seeds)) {
        deny all;
    }

    # Health check endpoint (no rate limiting)
    location = /health {
        try_files \$uri /index.php?\$query_string;
        access_log off;
    }
}
EOF

    log "SUCCESS" "Nginx configuration template created at config/nginx-site.conf"
    log "INFO" "To enable this site, copy the config to your web server:"
    log "INFO" "  sudo cp config/nginx-site.conf /etc/nginx/sites-available/aigov"
    log "INFO" "  sudo ln -sf /etc/nginx/sites-available/aigov /etc/nginx/sites-enabled/"
    log "INFO" "  sudo nginx -t && sudo systemctl reload nginx"
}

# Generate SSL setup instructions
generate_ssl_instructions() {
    log "INFO" "Generating SSL setup instructions..."

    cat > "${PROJECT_ROOT}/config/ssl-setup.md" <<EOF
# SSL Certificate Setup for aim.silverday.de

## Option 1: Let's Encrypt (if you have sudo access)
\`\`\`bash
sudo certbot --nginx -d aim.silverday.de
\`\`\`

## Option 2: Existing Certificate
If you have an existing SSL certificate, update the nginx configuration:
- Set ssl_certificate path to your certificate file
- Set ssl_certificate_key path to your private key file

## Option 3: Shared Hosting
If your hosting provider manages SSL certificates, they should automatically
work with the domain aim.silverday.de once the nginx configuration is applied.

## Verify SSL
After setup, verify SSL is working:
\`\`\`bash
curl -I https://aim.silverday.de/health
\`\`\`
EOF

    log "SUCCESS" "SSL setup instructions created at config/ssl-setup.md"
}

# Install application dependencies
install_app_dependencies() {
    log "INFO" "Installing application dependencies..."

    cd "${PROJECT_ROOT}"

    # Backend dependencies
    cd backend
    composer install --no-dev --optimize-autoloader --no-interaction

    # Frontend dependencies and build
    cd ../frontend
    npm ci --only=production
    npm run build

    # Set proper ownership
    chown -R ${DEPLOY_USER}:${DEPLOY_GROUP} "${PROJECT_ROOT}"

    log "SUCCESS" "Application dependencies installed"
}

# Configure environment
configure_environment() {
    log "INFO" "Configuring environment..."

    # Copy production environment file
    cp "${PROJECT_ROOT}/.env.production" "${PROJECT_ROOT}/.env"

    # Set proper permissions
    chmod 600 "${PROJECT_ROOT}/.env"
    chown ${DEPLOY_USER}:${DEPLOY_GROUP} "${PROJECT_ROOT}/.env"

    # Generate secure secrets if they haven't been customized
    if grep -q "CHANGE_THIS" "${PROJECT_ROOT}/.env"; then
        log "WARN" "Please update the CHANGE_THIS placeholders in .env with secure values"

        # Generate JWT secret
        jwt_secret=$(openssl rand -base64 32)
        sed -i "s/JWT_SECRET=CHANGE_THIS_JWT_SECRET_MIN_32_CHARS_CRYPTOGRAPHICALLY_SECURE/JWT_SECRET=${jwt_secret}/" "${PROJECT_ROOT}/.env"

        # Generate app key
        app_key=$(openssl rand -base64 32 | cut -c1-32)
        sed -i "s/APP_KEY=CHANGE_THIS_32_CHAR_ENCRYPTION_KEY/APP_KEY=${app_key}/" "${PROJECT_ROOT}/.env"

        # Generate maintenance secret
        maint_secret=$(openssl rand -hex 16)
        sed -i "s/MAINTENANCE_SECRET=CHANGE_THIS_MAINTENANCE_SECRET/MAINTENANCE_SECRET=${maint_secret}/" "${PROJECT_ROOT}/.env"
    fi

    log "SUCCESS" "Environment configured"
}

# Setup application database
setup_application() {
    log "INFO" "Setting up application..."

    # Generate database setup instructions
    cat > "${PROJECT_ROOT}/config/database-setup.sql" <<EOF
-- Database setup for AI Governance Platform
-- Run these commands in your MySQL/MariaDB console

CREATE DATABASE IF NOT EXISTS aigov_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'aigov_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON aigov_production.* TO 'aigov_user'@'localhost';
FLUSH PRIVILEGES;
EOF

    log "INFO" "Database setup SQL created at config/database-setup.sql"

    # Run migrations if database is configured
    if [[ -f "${PROJECT_ROOT}/.env" ]]; then
        source "${PROJECT_ROOT}/.env"
        if [[ -n "${DB_DATABASE:-}" ]]; then
            log "INFO" "Running database migrations..."
            cd "${PROJECT_ROOT}"

            if php scripts/migrate.php; then
                log "SUCCESS" "Database migrations completed"

                if php scripts/seed.php; then
                    log "SUCCESS" "Database seeding completed"
                else
                    log "WARN" "Database seeding failed - check database connection"
                fi
            else
                log "WARN" "Database migrations failed - check database connection and configuration"
            fi
        else
            log "WARN" "Database not configured in .env file"
        fi
    fi

    log "SUCCESS" "Application setup completed"
}

# Setup monitoring within project
setup_monitoring() {
    log "INFO" "Setting up monitoring within project..."

    # Create logrotate configuration template
    cat > "${PROJECT_ROOT}/config/logrotate.conf" <<EOF
# Logrotate configuration for AI Governance Platform
# Copy this to /etc/logrotate.d/aigov (requires sudo access)

${PROJECT_ROOT}/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
EOF

    # Create cron job template for monitoring
    cat > "${PROJECT_ROOT}/config/crontab.txt" <<EOF
# Crontab entries for AI Governance Platform
# Add these to your crontab with: crontab -e

# Run monitoring check every 5 minutes
*/5 * * * * cd ${PROJECT_ROOT} && php scripts/monitor.php >> storage/logs/monitor.log 2>&1

# Run daily backup at 2 AM
0 2 * * * cd ${PROJECT_ROOT} && ./scripts/backup.sh >> storage/logs/backup.log 2>&1

# Clean up old log files weekly
0 1 * * 0 find ${PROJECT_ROOT}/storage/logs -name "*.log" -mtime +30 -delete
EOF

    # Create simple monitoring runner
    cat > "${PROJECT_ROOT}/scripts/start-monitoring.sh" <<EOF
#!/bin/bash
# Simple monitoring runner (alternative to systemd service)
cd "${PROJECT_ROOT}"
while true; do
    php scripts/monitor.php
    sleep 300  # Run every 5 minutes
done
EOF

    chmod +x "${PROJECT_ROOT}/scripts/start-monitoring.sh"

    log "SUCCESS" "Monitoring templates created"
    log "INFO" "To enable monitoring, add the cron jobs from config/crontab.txt to your crontab"
    log "INFO" "Or run scripts/start-monitoring.sh in the background"
}

# Main deployment function
main() {
    log "INFO" "Starting production deployment..."

    # Parse arguments
    SKIP_BACKUP=false
    SKIP_TESTS=false

    for arg in "$@"; do
        case $arg in
            --skip-backup) SKIP_BACKUP=true ;;
            --skip-tests) SKIP_TESTS=true ;;
        esac
    done

    check_permissions

    # Create backup if not skipped
    if [[ "$SKIP_BACKUP" == false ]] && [[ -f "${PROJECT_ROOT}/.env" ]]; then
        log "INFO" "Creating backup..."
        backup_timestamp=$(date +%Y%m%d_%H%M%S)
        mkdir -p "${BACKUP_DIR}/${backup_timestamp}"
        cp -r "${PROJECT_ROOT}" "${BACKUP_DIR}/${backup_timestamp}/" 2>/dev/null || true
    fi

    create_directories
    check_system_dependencies
    install_app_dependencies
    configure_environment
    setup_application

    log "SUCCESS" "Application deployment completed successfully!"
    log "INFO" "Next steps:"
    log "INFO" "1. Set up your database using config/database-setup.sql"
    log "INFO" "2. Update database password and other settings in .env"
    log "INFO" "3. Test the application at https://aim.silverday.de"
    log "INFO" "4. Monitor logs in storage/logs/"
}

# Run main function
main "$@"