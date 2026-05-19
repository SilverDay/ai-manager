# Production Deployment Guide

This document describes how to deploy and maintain the AI Governance Platform in production.

## Quick Deployment

For a fully automated production setup on a fresh Ubuntu server:

```bash
# Clone the repository
git clone https://github.com/SilverDay/ai-manager.git
cd ai-manager

# Run the production deployment script (as root)
sudo ./scripts/deploy-production.sh
```

This script will:
- Install all system dependencies (PHP 8.3, Nginx, MariaDB, Node.js)
- Configure services and security settings
- Set up SSL certificates via Let's Encrypt
- Deploy the application and run migrations
- Configure automated backups and monitoring

## Manual Deployment Steps

If you prefer manual deployment or need to customize the setup:

### 1. Server Requirements

- **OS**: Ubuntu 20.04 LTS or newer
- **RAM**: Minimum 2GB (4GB recommended)
- **Disk**: Minimum 20GB free space
- **Network**: Public IP with domain pointing to it

### 2. Install System Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.3
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml \
  php8.3-mbstring php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath \
  php8.3-gd composer

# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
sudo apt install -y nodejs

# Install services
sudo apt install -y nginx mariadb-server redis-server fail2ban ufw
```

### 3. Configure Database

```bash
# Secure MariaDB installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -e "
CREATE DATABASE aigov_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aigov_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON aigov_production.* TO 'aigov_user'@'localhost';
FLUSH PRIVILEGES;
"
```

### 4. Application Setup

```bash
# Clone repository
git clone https://github.com/SilverDay/ai-manager.git /srv/vhosts/aim.silverday.de
cd /srv/vhosts/aim.silverday.de

# Set up environment
cp .env.production .env
# Edit .env with your database credentials and secure secrets

# Install PHP dependencies
cd backend && composer install --no-dev --optimize-autoloader

# Install and build frontend
cd ../frontend
npm ci --only=production
npm run build

# Set proper ownership
sudo chown -R www-data:www-data /srv/vhosts/aim.silverday.de

# Run migrations
php ../scripts/migrate.php
php ../scripts/seed.php
```

### 5. Configure Nginx

Use the configuration from `scripts/deploy-production.sh` or manually configure:

```bash
sudo cp config/nginx.conf /etc/nginx/sites-available/aigov
sudo ln -s /etc/nginx/sites-available/aigov /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx
```

### 6. SSL Certificate

```bash
sudo certbot --nginx -d aim.silverday.de
```

### 7. Configure Security

```bash
# Firewall
sudo ufw enable
sudo ufw allow 22,80,443/tcp

# Fail2ban
sudo cp config/security.conf /etc/fail2ban/jail.d/aigov.conf
sudo systemctl restart fail2ban
```

## Automated Deployment (GitHub Actions)

The repository includes GitHub Actions workflows for automated deployment:

### Setup GitHub Secrets

Add these secrets to your GitHub repository:

- `DEPLOY_SSH_KEY`: Private SSH key for deployment user
- `PRODUCTION_DB_PASSWORD`: Database password
- `JWT_SECRET`: JWT signing secret (32+ characters)
- `APP_KEY`: Application encryption key (32 characters)

### Deployment Process

1. Push to `main` branch triggers deployment
2. Tests run automatically
3. Application is built and deployed
4. Health checks verify deployment
5. Automatic rollback on failure

## Monitoring & Maintenance

### Health Monitoring

The application provides several monitoring endpoints:

- `GET /health` - Basic health check
- `GET /status` - Detailed system status (requires auth)
- `GET /metrics` - Performance metrics (requires auth)

### Automated Backups

Backups run automatically via cron:

```bash
# Add to crontab
0 2 * * * /srv/vhosts/aim.silverday.de/scripts/backup.sh
```

Manual backup:
```bash
sudo /srv/vhosts/aim.silverday.de/scripts/backup.sh --force
```

### Log Management

Logs are automatically rotated via logrotate:

- Application logs: `/var/log/aigov/`
- Nginx logs: `/var/log/nginx/`
- PHP logs: `/var/log/nginx/`

### Security Monitoring

```bash
# Run security audit
php scripts/security-audit.php

# Monitor system health
php scripts/monitor.php --verbose

# Check backup status
./scripts/backup.sh --status
```

## Database Maintenance

### Regular Maintenance

```bash
# Optimize database tables
mysql -u root -p aigov_production -e "OPTIMIZE TABLE \`users\`, \`ai_systems\`, \`audit_log\`;"

# Check database integrity
mysql -u root -p aigov_production -e "CHECK TABLE \`users\`, \`ai_systems\`;"

# Update database statistics
mysql -u root -p aigov_production -e "ANALYZE TABLE \`users\`, \`ai_systems\`, \`audit_log\`;"
```

### Backup Restoration

```bash
# List available backups
ls -la /var/backups/aigov/

# Restore database from backup
gunzip -c /var/backups/aigov/YYYYMMDD_HHMMSS/database/aigov_*.sql.gz | \
  mysql -u root -p aigov_production

# Restore files
sudo tar -xzf /var/backups/aigov/YYYYMMDD_HHMMSS/files/documents.tar.gz \
  -C /var/app/storage/
```

## Performance Tuning

### PHP-FPM Optimization

Edit `/etc/php/8.3/fpm/pool.d/aigov.conf`:

```ini
; For servers with more resources
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 10
pm.max_spare_servers = 20
```

### MySQL Optimization

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_type = 1
query_cache_size = 32M
```

### Nginx Caching

Add to nginx configuration:

```nginx
# Enable caching for static assets
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

## Troubleshooting

### Common Issues

1. **500 Internal Server Error**
   - Check PHP error logs: `/var/log/aigov/php-error.log`
   - Verify file permissions: `sudo chown -R www-data:www-data /srv/vhosts/aim.silverday.de`
   - Check nginx error log: `/var/log/nginx/error.log`

2. **Database Connection Failed**
   - Verify credentials in `.env`
   - Check MariaDB status: `sudo systemctl status mariadb`
   - Test connection: `mysql -u aigov_user -p aigov_production`

3. **SSL Certificate Issues**
   - Renew certificate: `sudo certbot renew`
   - Check certificate status: `sudo certbot certificates`

4. **High Memory Usage**
   - Check PHP memory limit
   - Monitor processes: `htop` or `ps aux`
   - Review slow query log

### Emergency Recovery

If the application is down:

1. **Check service status**
   ```bash
   sudo systemctl status nginx php8.3-fpm mariadb
   ```

2. **Rollback to previous version**
   ```bash
   # If using symlink deployment
   sudo mv /srv/vhosts/aim.silverday.de /srv/vhosts/aim.silverday.de.failed
   sudo mv /srv/vhosts/aim.silverday.de.old /srv/vhosts/aim.silverday.de
   sudo systemctl restart nginx php8.3-fpm
   ```

3. **Restore from backup**
   ```bash
   # Use most recent backup in /var/backups/aigov/
   ```

## Security Best Practices

- Keep system packages updated: `sudo apt update && sudo apt upgrade`
- Monitor security logs: `sudo fail2ban-client status`
- Regular security audits: `php scripts/security-audit.php`
- Review access logs for suspicious activity
- Rotate secrets periodically
- Use strong passwords and SSH keys only
- Monitor SSL certificate expiration

## Support and Documentation

- **Health checks**: Monitor `/health` endpoint
- **Logs**: Check `/var/log/aigov/` for application logs
- **Repository**: https://github.com/SilverDay/ai-manager
- **Issues**: Report at GitHub Issues

## Environment Variables Reference

See `.env.production` for a complete list of configuration options including:

- Database connection settings
- JWT and encryption secrets
- Rate limiting configuration
- File storage settings
- Email configuration
- Security headers
- Backup settings

Always use strong, unique passwords and secrets in production!