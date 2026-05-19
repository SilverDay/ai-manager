#!/bin/bash

# Docker Configuration Verification
# AI Governance Platform

echo "===================================================="
echo "Docker Stack Configuration Verification"
echo "===================================================="
echo ""

function check_file() {
    local file="$1"
    local description="$2"

    if [ -f "$file" ]; then
        echo "✅ $description: $file"
    else
        echo "❌ Missing $description: $file"
        return 1
    fi
}

function check_directory() {
    local dir="$1"
    local description="$2"

    if [ -d "$dir" ]; then
        echo "✅ $description: $dir"
    else
        echo "❌ Missing $description: $dir"
        return 1
    fi
}

# Check Docker configuration files
echo "📁 Docker Configuration Files:"
echo "------------------------------"
check_file "docker-compose.yml" "Main Docker Compose file"
check_file "docker-compose.override.yml" "Development overrides"
check_file "docker/php/Dockerfile" "PHP container Dockerfile"
check_file "docker/php/docker-entrypoint.sh" "PHP entrypoint script"
check_file "docker/apache/vhost.conf" "Apache virtual host config"

echo ""

# Check required directories
echo "📂 Required Directories:"
echo "-----------------------"
check_directory "docker" "Docker configuration"
check_directory "docker/php" "PHP Docker config"
check_directory "docker/apache" "Apache Docker config"
check_directory "storage" "File storage"
check_directory "storage/documents" "Document storage"
check_directory "storage/logs" "Log storage"

echo ""

# Check script files
echo "🔧 Management Scripts:"
echo "---------------------"
check_file "scripts/docker-dev.sh" "Docker development script"
if [ -x "scripts/docker-dev.sh" ]; then
    echo "   ✅ Script is executable"
else
    echo "   ⚠️  Script needs executable permission"
fi

echo ""

# Verify Docker Compose configuration
echo "🔍 Docker Compose Configuration:"
echo "-------------------------------"

if command -v docker >/dev/null 2>&1; then
    echo "✅ Docker command available"

    if docker compose version >/dev/null 2>&1; then
        echo "✅ Docker Compose V2 available"

        # Validate compose files
        if docker compose config >/dev/null 2>&1; then
            echo "✅ Docker Compose configuration is valid"

            echo ""
            echo "🐳 Configured Services:"
            echo "======================"
            docker compose config --services

        else
            echo "❌ Docker Compose configuration has errors"
            docker compose config
        fi
    else
        echo "⚠️  Docker Compose V2 not available, checking V1..."

        if command -v docker-compose >/dev/null 2>&1; then
            echo "✅ Docker Compose V1 available"
        else
            echo "❌ No Docker Compose found"
        fi
    fi
else
    echo "ℹ️  Docker not installed or not in PATH"
    echo "   Configuration files created successfully"
    echo "   To use the Docker stack:"
    echo "   1. Install Docker and Docker Compose"
    echo "   2. Run: ./scripts/docker-dev.sh start"
fi

echo ""

# Service endpoints documentation
echo "🌐 Service Endpoints (when running):"
echo "===================================="
echo "Frontend:     http://localhost:5173"
echo "Backend API:  http://localhost:8080"
echo "Health Check: http://localhost:8080/health"
echo "MailHog UI:   http://localhost:8025"
echo "Database:     localhost:3306"

echo ""

# Environment file check
echo "⚙️  Environment Configuration:"
echo "=============================="
if [ -f ".env" ]; then
    echo "✅ .env file exists"

    # Check for required environment variables for Docker
    required_vars=("DB_HOST" "DB_DATABASE" "DB_USERNAME" "DB_PASSWORD")
    for var in "${required_vars[@]}"; do
        if grep -q "^${var}=" .env; then
            echo "✅ $var configured"
        else
            echo "❌ $var missing from .env"
        fi
    done
else
    echo "❌ .env file not found"
    echo "   Copy .env.example to .env and configure your settings"
fi

echo ""
echo "✅ Docker stack configuration verification completed"
echo "   Ready for local development when Docker is installed"