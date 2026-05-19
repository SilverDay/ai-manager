#!/bin/bash

# Docker Development Stack Manager
# AI Governance Platform

set -e

PROJECT_NAME="aigov"
COMPOSE_FILE="docker-compose.yml"

function print_usage() {
    echo "Usage: $0 {start|stop|restart|status|logs|build|clean}"
    echo ""
    echo "Commands:"
    echo "  start    - Start all services"
    echo "  stop     - Stop all services"
    echo "  restart  - Restart all services"
    echo "  status   - Show service status"
    echo "  logs     - Show logs from all services"
    echo "  build    - Build custom images"
    echo "  clean    - Stop services and remove containers"
    echo "  health   - Check service health"
}

function start_services() {
    echo "🚀 Starting AI Governance Platform development stack..."
    docker-compose up -d
    echo "✅ Services started. Use 'docker-dev.sh status' to check health."
    echo ""
    echo "🌐 Frontend: http://localhost:5173"
    echo "🔧 Backend API: http://localhost:8080"
    echo "📧 MailHog: http://localhost:8025"
    echo "🗄️  Database: localhost:3306"
}

function stop_services() {
    echo "🛑 Stopping services..."
    docker-compose stop
    echo "✅ Services stopped."
}

function restart_services() {
    echo "🔄 Restarting services..."
    docker-compose restart
    echo "✅ Services restarted."
}

function show_status() {
    echo "📊 Service Status:"
    echo "=================="
    docker-compose ps
    echo ""
    echo "🏥 Health Checks:"
    echo "=================="
    for service in backend frontend mariadb mailhog; do
        health=$(docker inspect --format='{{.State.Health.Status}}' ${PROJECT_NAME}-${service/mariadb/db} 2>/dev/null || echo "no-healthcheck")
        case $health in
            "healthy")
                echo "✅ $service: healthy"
                ;;
            "unhealthy")
                echo "❌ $service: unhealthy"
                ;;
            "starting")
                echo "🔄 $service: starting..."
                ;;
            "no-healthcheck")
                echo "⚪ $service: running (no health check)"
                ;;
            *)
                echo "❓ $service: unknown ($health)"
                ;;
        esac
    done
}

function show_logs() {
    echo "📋 Service Logs:"
    echo "================"
    docker-compose logs -f
}

function build_images() {
    echo "🔨 Building custom images..."
    docker-compose build --no-cache
    echo "✅ Images built successfully."
}

function clean_services() {
    echo "🧹 Cleaning up containers and networks..."
    docker-compose down --remove-orphans
    echo "✅ Cleanup completed."
}

function check_health() {
    echo "🏥 Detailed Health Check:"
    echo "========================"

    # Check database
    echo -n "Database connection: "
    if docker-compose exec -T mariadb mysql -u aigov_user -p'[jxm!0O5M6f7RziQ' -e "SELECT 1" aigov >/dev/null 2>&1; then
        echo "✅ OK"
    else
        echo "❌ Failed"
    fi

    # Check backend API
    echo -n "Backend API health: "
    if curl -sf http://localhost:8080/health >/dev/null 2>&1; then
        echo "✅ OK"
    else
        echo "❌ Failed"
    fi

    # Check frontend
    echo -n "Frontend server: "
    if curl -sf http://localhost:5173 >/dev/null 2>&1; then
        echo "✅ OK"
    else
        echo "❌ Failed"
    fi

    # Check MailHog
    echo -n "MailHog server: "
    if curl -sf http://localhost:8025 >/dev/null 2>&1; then
        echo "✅ OK"
    else
        echo "❌ Failed"
    fi
}

# Main command handling
case "${1:-}" in
    start)
        start_services
        ;;
    stop)
        stop_services
        ;;
    restart)
        restart_services
        ;;
    status)
        show_status
        ;;
    logs)
        show_logs
        ;;
    build)
        build_images
        ;;
    clean)
        clean_services
        ;;
    health)
        check_health
        ;;
    *)
        print_usage
        exit 1
        ;;
esac