#!/bin/bash

set -euo pipefail

# Production Deployment Script for AI Governance Platform
# Usage: ./scripts/deploy-production.sh [--skip-backup] [--skip-tests]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
DEPLOY_USER="www-data"
DEPLOY_GROUP="www-data"
BACKUP_DIR="/var/backups/aigov"
LOG_FILE="/var/log/aigov/deploy.log"

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

# Check if running as root or with sudo
check_permissions() {
    if [[ $EUID -ne 0 ]]; then
        log "ERROR" "This script must be run as root or with sudo"
        exit 1
    fi
}

# Create necessary directories
create_directories() {
    log "INFO" "Creating necessary directories..."

    mkdir -p /var/log/aigov
    mkdir -p /var/app/storage/documents
    mkdir -p /var/backups/aigov
    mkdir -p /etc/aigov

    # Set proper ownership
    chown -R ${DEPLOY_USER}:${DEPLOY_GROUP} /var/app/storage
    chown -R ${DEPLOY_USER}:${DEPLOY_GROUP} /var/log/aigov

    # Set proper permissions
    chmod 750 /var/app/storage
    chmod 750 /var/log/aigov
    chmod 700 /var/backups/aigov

    log "SUCCESS" "Directories created successfully"
}

# Install system dependencies
install_system_dependencies() {
    log "INFO" "Installing system dependencies..."

    # Update package list
    apt-get update -qq

    # Install PHP 8.3 and required extensions
    apt-get install -y \
        php8.3-fpm \
        php8.3-cli \
        php8.3-mysql \
        php8.3-xml \
        php8.3-mbstring \
        php8.3-curl \
        php8.3-zip \
        php8.3-intl \
        php8.3-bcmath \
        php8.3-gd \
        php8.3-redis \
        composer \
        nginx \
        mysql-server \
        redis-server \
        certbot \
        python3-certbot-nginx \
        logrotate \
        fail2ban

    # Install Node.js 20
    if ! command -v node &> /dev/null || [[ $(node -v | cut -d'v' -f2 | cut -d'.' -f1) -lt 20 ]]; then
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
        apt-get install -y nodejs
    fi

    log "SUCCESS" "System dependencies installed"
}

# Configure PHP
configure_php() {
    log "INFO" "Configuring PHP for production..."

    # PHP-FPM configuration
    cat > /etc/php/8.3/fpm/pool.d/aigov.conf <<EOF
[aigov]
user = ${DEPLOY_USER}
group = ${DEPLOY_GROUP}
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

; Security
php_admin_value[expose_php] = off
php_admin_value[display_errors] = off
php_admin_value[log_errors] = on
php_admin_value[error_log] = /var/log/aigov/php-error.log
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

    # Restart PHP-FPM
    systemctl restart php8.3-fpm
    systemctl enable php8.3-fpm

    log "SUCCESS" "PHP configured"
}

# Configure Nginx
configure_nginx() {
    log "INFO" "Configuring Nginx..."

    # Remove default site
    rm -f /etc/nginx/sites-enabled/default

    # Create Nginx configuration
    cat > /etc/nginx/sites-available/aigov <<EOF
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

    # Enable site
    ln -sf /etc/nginx/sites-available/aigov /etc/nginx/sites-enabled/

    # Test configuration
    nginx -t

    # Restart Nginx
    systemctl restart nginx
    systemctl enable nginx

    log "SUCCESS" "Nginx configured"
}

# Setup SSL certificate
setup_ssl() {
    log "INFO" "Setting up SSL certificate..."

    # Stop Nginx temporarily for certificate generation
    systemctl stop nginx

    # Generate certificate
    certbot certonly --standalone -d aim.silverday.de --non-interactive --agree-tos --email klingner@silverday.de

    # Setup auto-renewal
    systemctl enable certbot.timer

    # Start Nginx again
    systemctl start nginx

    log "SUCCESS" "SSL certificate configured"
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

# Setup database
setup_database() {
    log "INFO" "Setting up production database..."

    # Create database and user
    mysql -e "CREATE DATABASE IF NOT EXISTS aigov_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS 'aigov_user'@'localhost' IDENTIFIED BY 'TEMP_PASSWORD';"
    mysql -e "GRANT ALL PRIVILEGES ON aigov_production.* TO 'aigov_user'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"

    log "WARN" "Please update the database password in .env and MySQL: ALTER USER 'aigov_user'@'localhost' IDENTIFIED BY 'your_secure_password';"

    # Run migrations
    cd "${PROJECT_ROOT}"
    php scripts/migrate.php
    php scripts/seed.php

    log "SUCCESS" "Database setup completed"
}

# Setup monitoring and logging
setup_monitoring() {
    log "INFO" "Setting up monitoring and logging..."

    # Logrotate configuration
    cat > /etc/logrotate.d/aigov <<EOF
/var/log/aigov/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 ${DEPLOY_USER} ${DEPLOY_GROUP}
    postrotate
        systemctl reload php8.3-fpm
    endscript
}
EOF

    # Create systemd service for monitoring
    cat > /etc/systemd/system/aigov-monitor.service <<EOF
[Unit]
Description=AI Governance Platform Monitoring
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/php ${PROJECT_ROOT}/scripts/monitor.php
Restart=always
User=${DEPLOY_USER}
Group=${DEPLOY_GROUP}

[Install]
WantedBy=multi-user.target
EOF

    log "SUCCESS" "Monitoring and logging configured"
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
        cp -r "${PROJECT_ROOT}" "${BACKUP_DIR}/${backup_timestamp}/"
        mysqldump --defaults-extra-file=/etc/mysql/debian.cnf aigov_production > "${BACKUP_DIR}/${backup_timestamp}/database.sql" 2>/dev/null || true
    fi

    create_directories
    install_system_dependencies
    configure_php
    configure_nginx
    setup_ssl
    install_app_dependencies
    configure_environment
    setup_database
    setup_monitoring

    # Final security hardening
    ufw --force enable
    ufw allow 22
    ufw allow 80
    ufw allow 443

    log "SUCCESS" "Production deployment completed successfully!"
    log "INFO" "Next steps:"
    log "INFO" "1. Update database password in .env and MySQL"
    log "INFO" "2. Configure email settings in .env"
    log "INFO" "3. Test the application at https://aim.silverday.de"
    log "INFO" "4. Monitor logs in /var/log/aigov/"
}

# Run main function
main "$@"