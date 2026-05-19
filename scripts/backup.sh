#!/bin/bash

set -euo pipefail

# Production Backup Script for AI Governance Platform
# Performs database backups, file backups, and cleanup

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
BACKUP_BASE_DIR="${PROJECT_ROOT}/storage/backups"
LOG_FILE="${PROJECT_ROOT}/storage/logs/backup.log"
RETENTION_DAYS=30

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# Load database configuration from .env
load_db_config() {
    if [[ ! -f "${PROJECT_ROOT}/.env" ]]; then
        log "ERROR" "Environment file not found: ${PROJECT_ROOT}/.env"
        exit 1
    fi

    # Source database configuration
    export $(grep -E '^DB_' "${PROJECT_ROOT}/.env" | xargs)

    if [[ -z "${DB_DATABASE:-}" || -z "${DB_USERNAME:-}" || -z "${DB_PASSWORD:-}" ]]; then
        log "ERROR" "Database configuration incomplete in .env file"
        exit 1
    fi
}

# Create backup directory structure
setup_backup_dirs() {
    local backup_date=$(date '+%Y%m%d_%H%M%S')
    BACKUP_DIR="${BACKUP_BASE_DIR}/${backup_date}"

    log "INFO" "Creating backup directory: ${BACKUP_DIR}"

    mkdir -p "${BACKUP_DIR}/database"
    mkdir -p "${BACKUP_DIR}/files"
    mkdir -p "${BACKUP_DIR}/logs"
    mkdir -p "${BACKUP_DIR}/config"

    chmod 700 "${BACKUP_DIR}"
}

# Backup database
backup_database() {
    log "INFO" "Starting database backup..."

    local dump_file="${BACKUP_DIR}/database/aigov_${DB_DATABASE}_$(date '+%Y%m%d_%H%M%S').sql"
    local compressed_file="${dump_file}.gz"

    # Create MySQL config file for secure connection
    local mysql_config=$(mktemp)
    cat > "${mysql_config}" <<EOF
[client]
host=${DB_HOST:-localhost}
port=${DB_PORT:-3306}
user=${DB_USERNAME}
password=${DB_PASSWORD}
EOF

    chmod 600 "${mysql_config}"

    # Perform database dump
    if mysqldump --defaults-file="${mysql_config}" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --add-drop-table \
        --add-locks \
        --create-options \
        --disable-keys \
        --extended-insert \
        --quick \
        "${DB_DATABASE}" > "${dump_file}"; then

        # Compress the dump
        gzip "${dump_file}"

        # Verify compressed backup
        if [[ -f "${compressed_file}" ]] && [[ $(stat -f%z "${compressed_file}" 2>/dev/null || stat -c%s "${compressed_file}" 2>/dev/null) -gt 1024 ]]; then
            log "SUCCESS" "Database backup completed: ${compressed_file}"

            # Save backup metadata
            cat > "${BACKUP_DIR}/database/backup_info.json" <<EOF
{
    "database": "${DB_DATABASE}",
    "backup_time": "$(date -Iseconds)",
    "backup_file": "$(basename "${compressed_file}")",
    "file_size_bytes": $(stat -f%z "${compressed_file}" 2>/dev/null || stat -c%s "${compressed_file}" 2>/dev/null),
    "mysql_version": "$(mysql --defaults-file="${mysql_config}" -e "SELECT VERSION();" -B -N 2>/dev/null || echo "unknown")"
}
EOF
        else
            log "ERROR" "Database backup verification failed"
            rm -f "${mysql_config}"
            return 1
        fi
    else
        log "ERROR" "Database backup failed"
        rm -f "${mysql_config}"
        return 1
    fi

    rm -f "${mysql_config}"
}

# Backup application files
backup_files() {
    log "INFO" "Starting file backup..."

    # Backup uploaded documents
    if [[ -d "${PROJECT_ROOT}/storage/documents" ]]; then
        tar -czf "${BACKUP_DIR}/files/documents.tar.gz" \
            -C "${PROJECT_ROOT}/storage" documents/
        log "SUCCESS" "Documents backup completed"
    fi

    # Backup configuration files
    cp "${PROJECT_ROOT}/.env" "${BACKUP_DIR}/config/env" 2>/dev/null || true

    if [[ -f "/etc/nginx/sites-available/aigov" ]]; then
        cp "/etc/nginx/sites-available/aigov" "${BACKUP_DIR}/config/nginx.conf"
    fi

    if [[ -f "/etc/php/8.3/fpm/pool.d/aigov.conf" ]]; then
        cp "/etc/php/8.3/fpm/pool.d/aigov.conf" "${BACKUP_DIR}/config/php-fpm.conf"
    fi

    # Note: SSL certificates are managed by the hosting provider
    # and don't need to be backed up in a shared hosting environment

    log "SUCCESS" "File backup completed"
}

# Backup recent logs
backup_logs() {
    log "INFO" "Starting log backup..."

    # Backup last 7 days of logs
    find "${PROJECT_ROOT}/storage/logs" -name "*.log" -mtime -7 -type f | \
        tar -czf "${BACKUP_DIR}/logs/recent-logs.tar.gz" -T - 2>/dev/null || true

    # Note: Web server logs are managed by the hosting provider
    # and are typically not accessible in shared hosting environments

    log "SUCCESS" "Log backup completed"
}

# Create backup summary
create_backup_summary() {
    log "INFO" "Creating backup summary..."

    local total_size=$(du -sh "${BACKUP_DIR}" | cut -f1)

    cat > "${BACKUP_DIR}/backup_summary.json" <<EOF
{
    "backup_id": "$(basename "${BACKUP_DIR}")",
    "backup_time": "$(date -Iseconds)",
    "backup_type": "full",
    "server_hostname": "$(hostname)",
    "total_size": "${total_size}",
    "retention_until": "$(date -d "+${RETENTION_DAYS} days" -Iseconds)",
    "components": {
        "database": $([ -f "${BACKUP_DIR}/database/backup_info.json" ] && echo "true" || echo "false"),
        "documents": $([ -f "${BACKUP_DIR}/files/documents.tar.gz" ] && echo "true" || echo "false"),
        "configuration": $([ -f "${BACKUP_DIR}/config/env" ] && echo "true" || echo "false"),
        "logs": $([ -f "${BACKUP_DIR}/logs/recent-logs.tar.gz" ] && echo "true" || echo "false")
    }
}
EOF

    log "SUCCESS" "Backup summary created: ${total_size} total"
}

# Cleanup old backups
cleanup_old_backups() {
    log "INFO" "Cleaning up backups older than ${RETENTION_DAYS} days..."

    local deleted_count=0

    # Find and delete old backup directories
    while IFS= read -r -d '' backup_path; do
        local backup_name=$(basename "${backup_path}")
        log "INFO" "Removing old backup: ${backup_name}"
        rm -rf "${backup_path}"
        ((deleted_count++))
    done < <(find "${BACKUP_BASE_DIR}" -maxdepth 1 -type d -name "20*" -mtime +${RETENTION_DAYS} -print0 2>/dev/null || true)

    if [[ ${deleted_count} -gt 0 ]]; then
        log "SUCCESS" "Cleaned up ${deleted_count} old backups"
    else
        log "INFO" "No old backups to clean up"
    fi
}

# Verify backup integrity
verify_backup() {
    log "INFO" "Verifying backup integrity..."

    local errors=0

    # Verify database backup
    if [[ -f "${BACKUP_DIR}/database"/*.sql.gz ]]; then
        if ! gzip -t "${BACKUP_DIR}/database"/*.sql.gz; then
            log "ERROR" "Database backup file is corrupted"
            ((errors++))
        fi
    else
        log "WARN" "No database backup found"
    fi

    # Verify file backups
    for tar_file in "${BACKUP_DIR}/files"/*.tar.gz "${BACKUP_DIR}/logs"/*.tar.gz "${BACKUP_DIR}/config"/*.tar.gz; do
        if [[ -f "${tar_file}" ]]; then
            if ! tar -tzf "${tar_file}" >/dev/null 2>&1; then
                log "ERROR" "Archive file is corrupted: $(basename "${tar_file}")"
                ((errors++))
            fi
        fi
    done

    if [[ ${errors} -eq 0 ]]; then
        log "SUCCESS" "Backup verification passed"
        return 0
    else
        log "ERROR" "Backup verification failed with ${errors} errors"
        return 1
    fi
}

# Send backup notification
send_notification() {
    local status=$1
    local message=$2

    # Log the notification
    log "INFO" "Backup ${status}: ${message}"

    # Here you could integrate with external notification systems
    # Example: send email, Slack notification, etc.

    # Write status to a file for monitoring systems
    echo "${status}" > "${PROJECT_ROOT}/storage/logs/last_backup_status"
    echo "$(date -Iseconds)" > "${PROJECT_ROOT}/storage/logs/last_backup_time"
}

# Main backup function
main() {
    log "INFO" "Starting production backup process..."

    # Parse arguments
    FORCE_BACKUP=false
    SKIP_CLEANUP=false

    for arg in "$@"; do
        case $arg in
            --force) FORCE_BACKUP=true ;;
            --skip-cleanup) SKIP_CLEANUP=true ;;
        esac
    done

    # Check if backup is needed (unless forced)
    if [[ "$FORCE_BACKUP" == false ]]; then
        local last_backup=$(find "${BACKUP_BASE_DIR}" -maxdepth 1 -type d -name "20*" | sort | tail -1)
        if [[ -n "$last_backup" ]]; then
            local last_backup_time=$(stat -f%B "${last_backup}" 2>/dev/null || stat -c%Y "${last_backup}" 2>/dev/null)
            local hours_since_backup=$(( ($(date +%s) - last_backup_time) / 3600 ))

            if [[ $hours_since_backup -lt 23 ]]; then
                log "INFO" "Recent backup exists (${hours_since_backup}h ago), skipping. Use --force to override."
                exit 0
            fi
        fi
    fi

    # Load configuration
    load_db_config

    # Setup backup structure
    setup_backup_dirs

    # Perform backups
    backup_database || {
        send_notification "FAILED" "Database backup failed"
        exit 1
    }

    backup_files
    backup_logs
    create_backup_summary

    # Verify backup
    if verify_backup; then
        send_notification "SUCCESS" "Backup completed successfully: $(basename "${BACKUP_DIR}")"
    else
        send_notification "FAILED" "Backup verification failed"
        exit 1
    fi

    # Cleanup old backups
    if [[ "$SKIP_CLEANUP" == false ]]; then
        cleanup_old_backups
    fi

    # Final summary
    local backup_size=$(du -sh "${BACKUP_DIR}" | cut -f1)
    log "SUCCESS" "Backup process completed. Size: ${backup_size}, Location: ${BACKUP_DIR}"
}

# Error handler
error_handler() {
    local line_number=$1
    log "ERROR" "Backup script failed at line ${line_number}"
    send_notification "FAILED" "Backup script failed at line ${line_number}"
    exit 1
}

trap 'error_handler ${LINENO}' ERR

# Execute main function
main "$@"