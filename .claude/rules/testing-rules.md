# Testing Rules

## Backend (PHPUnit)

### Test Structure

```
backend/tests/
├── Unit/
│   ├── Services/
│   │   ├── AiSystemServiceTest.php
│   │   ├── AuthServiceTest.php
│   │   └── ...
│   ├── Validators/
│   │   └── AiSystemValidatorTest.php
│   └── Helpers/
├── Integration/
│   ├── Controllers/
│   │   ├── AuthControllerTest.php
│   │   ├── AiSystemControllerTest.php
│   │   └── ...
│   └── TenantIsolationTest.php
└── TestCase.php              # Base class with DB setup/teardown
```

### Test Database

Tests use a separate `aigov_test` database. `TestCase.php` runs migrations, seeds, and wraps each test in a transaction that rolls back.

### Tenant Isolation Tests

Every controller test class MUST include a tenant isolation test:

```php
public function test_tenant_a_cannot_see_tenant_b_data(): void
{
    // Create a resource in tenant A
    $systemA = $this->createAiSystem(tenantId: $this->tenantA->id);

    // Authenticate as tenant B user
    $this->actingAs($this->tenantBUser);

    // Attempt to access tenant A's resource
    $response = $this->get("/api/v1/ai-systems/{$systemA->id}");

    $this->assertEquals(404, $response->getStatusCode());
}

public function test_list_endpoint_returns_only_own_tenant_data(): void
{
    $this->createAiSystem(tenantId: $this->tenantA->id);
    $this->createAiSystem(tenantId: $this->tenantB->id);

    $this->actingAs($this->tenantAUser);
    $response = $this->get('/api/v1/ai-systems');
    $data = json_decode($response->getBody(), true);

    foreach ($data['data'] as $system) {
        $this->assertEquals($this->tenantA->id, $system['tenant_id']);
    }
}
```

### Minimum Test Coverage Per Endpoint

1. **Happy path** — valid request, correct response structure, correct HTTP status.
2. **Authentication** — request without token returns 401.
3. **Authorisation** — request with wrong role returns 403.
4. **Validation** — missing/invalid fields return 422 with field-level errors.
5. **Tenant isolation** — cross-tenant access returns 404.
6. **Not found** — nonexistent resource returns 404.

### Naming Convention

Test methods: `test_{action}_{scenario}_{expected_result}`

```php
test_create_ai_system_with_valid_data_returns_201()
test_create_ai_system_without_name_returns_422()
test_create_ai_system_without_auth_returns_401()
test_get_ai_system_from_other_tenant_returns_404()
```

## Frontend (Vitest)

### Test Structure

```
frontend/src/
├── components/
│   └── __tests__/
│       └── AiSystemCard.test.js
├── stores/
│   └── __tests__/
│       └── aiSystems.test.js
├── api/
│   └── __tests__/
│       └── aiSystems.test.js
└── composables/
    └── __tests__/
        └── useWizard.test.js
```

### What to Test

- **Stores:** action logic, state mutations, computed properties. Mock API calls.
- **Composables:** shared logic in isolation.
- **Components:** only test complex components with significant logic. Simple display components don't need unit tests.
- **API modules:** test request construction and response transformation. Mock Axios.

### E2E (Cypress, Phase 2)

- Test critical user flows: login, register AI system, complete classification wizard, submit for approval.
- Run against staging environment with seeded test data.
