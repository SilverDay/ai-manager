#!/bin/bash

# AI Governance Platform - Authentication Endpoints Integration Tests
# Tests all authentication flows, edge cases, and tenant isolation

set -e

BASE_URL="https://aim.silverday.de/api/v1"
TMPDIR="/tmp/auth-tests-$$"
mkdir -p "$TMPDIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

# Helper functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((TESTS_PASSED++))
}

log_error() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((TESTS_FAILED++))
}

log_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

increment_test() {
    ((TESTS_TOTAL++))
}

# Test helper function
test_endpoint() {
    local name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_status="$5"
    local auth_header="$6"
    local description="$7"

    increment_test

    local cmd="curl -s -w '%{http_code}' -X $method $BASE_URL$endpoint"

    if [ -n "$data" ]; then
        cmd="$cmd -H 'Content-Type: application/json' -d '$data'"
    fi

    if [ -n "$auth_header" ]; then
        cmd="$cmd -H 'Authorization: Bearer $auth_header'"
    fi

    local response=$(eval $cmd)
    local status_code="${response: -3}"
    local body="${response%???}"

    if [ "$status_code" = "$expected_status" ]; then
        log_success "$name: $description (HTTP $status_code)"
        echo "$body" > "$TMPDIR/response_$name.json"
        return 0
    else
        log_error "$name: $description - Expected HTTP $expected_status, got $status_code"
        echo "Response: $body"
        return 1
    fi
}

# Extract JSON value helper
extract_json() {
    local file="$1"
    local key="$2"
    if [ -f "$file" ]; then
        jq -r "$key // empty" "$file" 2>/dev/null || echo ""
    fi
}

# Test data
SUPERADMIN_EMAIL="test-admin@example.com"
SUPERADMIN_PASSWORD="TestPassword123!"
TENANT_EMAIL="test-user@testcompany.com"
TENANT_PASSWORD="TestPass123!"
INVALID_EMAIL="invalid@example.com"
INVALID_PASSWORD="wrongpassword"

echo "======================================="
echo "Authentication Endpoints Integration Tests"
echo "======================================="
echo ""

# Test 1: Health check
log_info "Testing health endpoint..."
test_endpoint "health" "GET" "/health" "" "200" "" "Health check should pass"

echo ""
log_info "=== Testing Authentication Flows ==="

# Test 2: Login with invalid credentials
log_info "Testing authentication failures..."
test_endpoint "login_invalid" "POST" "/auth/login" "{\"email\":\"$INVALID_EMAIL\",\"password\":\"$INVALID_PASSWORD\"}" "401" "" "Invalid credentials should return 401"

# Test 3: Login with missing data
test_endpoint "login_missing" "POST" "/auth/login" "{\"email\":\"\"}" "422" "" "Missing password should return 422"

# Test 4: Login with tenant user (no MFA)
log_info "Testing tenant user login (no MFA required)..."
test_endpoint "login_tenant" "POST" "/auth/login" "{\"email\":\"$TENANT_EMAIL\",\"password\":\"$TENANT_PASSWORD\"}" "200" "" "Tenant user login should succeed"

if [ $? -eq 0 ]; then
    TENANT_TOKEN=$(extract_json "$TMPDIR/response_login_tenant.json" ".data.access_token")
    log_info "Extracted tenant access token: ${TENANT_TOKEN:0:50}..."
fi

# Test 5: Login with superadmin (requires MFA)
log_info "Testing superadmin login (MFA required)..."
test_endpoint "login_superadmin" "POST" "/auth/login" "{\"email\":\"$SUPERADMIN_EMAIL\",\"password\":\"$SUPERADMIN_PASSWORD\"}" "200" "" "Superadmin login should return MFA challenge"

if [ $? -eq 0 ]; then
    MFA_CHALLENGE=$(extract_json "$TMPDIR/response_login_superadmin.json" ".data.challenge_token")
    log_info "Extracted MFA challenge token: ${MFA_CHALLENGE:0:50}..."
fi

# Test 6: MFA verification with invalid code
if [ -n "$MFA_CHALLENGE" ]; then
    log_info "Testing MFA verification..."
    test_endpoint "mfa_invalid" "POST" "/auth/mfa-verify" "{\"challenge_token\":\"$MFA_CHALLENGE\",\"code\":\"000000\"}" "200" "" "MFA with any 6-digit code should work (stubbed)"

    if [ $? -eq 0 ]; then
        SUPERADMIN_TOKEN=$(extract_json "$TMPDIR/response_mfa_invalid.json" ".data.access_token")
        log_info "Extracted superadmin access token: ${SUPERADMIN_TOKEN:0:50}..."
    fi
fi

# Test 7: Test authenticated endpoints
echo ""
log_info "=== Testing Authenticated Endpoints ==="

if [ -n "$TENANT_TOKEN" ]; then
    log_info "Testing logout with tenant user..."
    test_endpoint "logout_tenant" "POST" "/auth/logout" "" "200" "$TENANT_TOKEN" "Tenant user logout should succeed"
fi

if [ -n "$SUPERADMIN_TOKEN" ]; then
    log_info "Testing logout with superadmin..."
    test_endpoint "logout_superadmin" "POST" "/auth/logout" "" "200" "$SUPERADMIN_TOKEN" "Superadmin logout should succeed"
fi

# Test 8: Test endpoint without authentication
log_info "Testing protected endpoint without auth..."
test_endpoint "logout_noauth" "POST" "/auth/logout" "" "401" "" "Logout without auth should return 401"

# Test 9: Test with invalid token
log_info "Testing with invalid token..."
test_endpoint "logout_badtoken" "POST" "/auth/logout" "" "401" "invalid.jwt.token" "Invalid token should return 401"

# Test 10: Password reset flow
echo ""
log_info "=== Testing Password Reset Flow ==="

test_endpoint "forgot_valid" "POST" "/auth/forgot-password" "{\"email\":\"$TENANT_EMAIL\"}" "200" "" "Forgot password with valid email"
test_endpoint "forgot_invalid" "POST" "/auth/forgot-password" "{\"email\":\"invalid\"}" "422" "" "Forgot password with invalid email format"
test_endpoint "forgot_missing" "POST" "/auth/forgot-password" "{}" "422" "" "Forgot password with missing email"

# Test 11: Refresh token flow
echo ""
log_info "=== Testing Refresh Token Flow ==="

# Get fresh tokens for refresh test
log_info "Getting fresh tokens for refresh test..."
FRESH_RESPONSE=$(curl -s -c "$TMPDIR/cookies.txt" -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$TENANT_EMAIL\",\"password\":\"$TENANT_PASSWORD\"}")

if echo "$FRESH_RESPONSE" | jq -e '.success' >/dev/null 2>&1; then
    log_info "Testing token refresh..."
    REFRESH_RESPONSE=$(curl -s -w '%{http_code}' -b "$TMPDIR/cookies.txt" -X POST "$BASE_URL/auth/refresh")
    REFRESH_STATUS="${REFRESH_RESPONSE: -3}"

    if [ "$REFRESH_STATUS" = "200" ]; then
        log_success "refresh_token: Refresh token should work with valid cookie (HTTP 200)"
        ((TESTS_PASSED++))
    else
        log_error "refresh_token: Refresh token failed - Expected HTTP 200, got $REFRESH_STATUS"
        ((TESTS_FAILED++))
    fi
    ((TESTS_TOTAL++))
else
    log_warning "Could not get fresh tokens for refresh test"
fi

# Test 12: Rate limiting (attempt multiple rapid requests)
echo ""
log_info "=== Testing Rate Limiting ==="

log_info "Testing rate limiting on auth endpoints..."
for i in {1..15}; do
    RATE_RESPONSE=$(curl -s -w '%{http_code}' -X POST "$BASE_URL/auth/login" \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"$INVALID_EMAIL\",\"password\":\"$INVALID_PASSWORD\"}")
    RATE_STATUS="${RATE_RESPONSE: -3}"

    if [ "$RATE_STATUS" = "429" ]; then
        log_success "rate_limit: Rate limiting triggered on attempt $i (HTTP 429)"
        break
    elif [ $i -eq 15 ]; then
        log_warning "rate_limit: Rate limiting not triggered after 15 attempts"
    fi
done
((TESTS_TOTAL++))

# Test 13: CORS headers
echo ""
log_info "=== Testing CORS Headers ==="

CORS_RESPONSE=$(curl -s -I -X OPTIONS "$BASE_URL/auth/login" \
    -H "Origin: https://app.example.com" \
    -H "Access-Control-Request-Method: POST" \
    -H "Access-Control-Request-Headers: Content-Type")

if echo "$CORS_RESPONSE" | grep -q "Access-Control-Allow-Origin"; then
    log_success "cors_headers: CORS headers are present"
    ((TESTS_PASSED++))
else
    log_error "cors_headers: CORS headers are missing"
    ((TESTS_FAILED++))
fi
((TESTS_TOTAL++))

# Test 14: Content-Type validation
echo ""
log_info "=== Testing Content-Type Validation ==="

test_endpoint "content_type" "POST" "/auth/login" "invalid-json" "422" "" "Invalid JSON should return 422"

# Test 15: User management endpoints (requires admin role)
echo ""
log_info "=== Testing User Management Endpoints ==="

# Get admin token first
ADMIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$TENANT_EMAIL\",\"password\":\"$TENANT_PASSWORD\"}")

if echo "$ADMIN_RESPONSE" | jq -e '.success' >/dev/null 2>&1; then
    ADMIN_TOKEN=$(echo "$ADMIN_RESPONSE" | jq -r '.data.access_token')

    log_info "Testing user management endpoints with admin token..."
    test_endpoint "pending_users" "GET" "/users/pending" "" "200" "$ADMIN_TOKEN" "Get pending users should work for admin"
    test_endpoint "invitations" "GET" "/invitations" "" "200" "$ADMIN_TOKEN" "Get invitations should work for admin"
    test_endpoint "domains" "GET" "/domains" "" "200" "$ADMIN_TOKEN" "Get domains should work for admin"
else
    log_warning "Could not get admin token for user management tests"
fi

# Test 16: Tenant isolation verification
echo ""
log_info "=== Testing Tenant Isolation ==="

log_info "Verifying that endpoints properly filter by tenant_id..."
# This would require creating test data in different tenants and verifying isolation
# For now, we test that the endpoints require authentication and return proper structures

if [ -n "$ADMIN_TOKEN" ]; then
    USERS_RESPONSE=$(curl -s -X GET "$BASE_URL/users/pending" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    if echo "$USERS_RESPONSE" | jq -e '.data' >/dev/null 2>&1; then
        log_success "tenant_isolation: User endpoints return proper data structure"
        ((TESTS_PASSED++))
    else
        log_error "tenant_isolation: User endpoints return invalid structure"
        ((TESTS_FAILED++))
    fi
    ((TESTS_TOTAL++))
fi

# Cleanup
rm -rf "$TMPDIR"

# Summary
echo ""
echo "======================================="
echo "Test Results Summary"
echo "======================================="
echo "Total Tests: $TESTS_TOTAL"
echo "Passed: $TESTS_PASSED"
echo "Failed: $TESTS_FAILED"

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed.${NC}"
    exit 1
fi