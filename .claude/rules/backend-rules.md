# Backend PHP Rules

Applied when working in `backend/` directory.

## Request Lifecycle

Every API request follows this exact flow:

```
Apache → public/index.php → Router::dispatch()
  → CorsMiddleware
  → RateLimitMiddleware
  → AuthMiddleware (JWT validation, sets $request->user)
  → TenantMiddleware (extracts tenant_id from JWT, sets $request->tenantId)
  → RbacMiddleware (checks user roles against route requirements)
  → Controller::action($request)
    → Validator::validate($request->body)
    → Service::method($validatedData, $tenantId)
      → Model::query($tenantId, ...)
    → Response::json($envelope)
```

Do not skip layers. Controllers call Services, Services call Models. Controllers never query the DB directly.

## Model Pattern

Every tenant-scoped Model method must accept and use `int $tenantId` as its first parameter:

```php
public function findById(int $tenantId, int $id): ?array
{
    $stmt = $this->db->prepare(
        'SELECT * FROM ai_systems WHERE tenant_id = ? AND id = ?'
    );
    $stmt->execute([$tenantId, $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
```

Never rely on a global tenant context inside the Model. Always pass it explicitly.

## Audit Logging

Use the `AuditService::log()` helper for every material action:

```php
$this->auditService->log(
    tenantId: $tenantId,       // nullable for platform actions
    userId: $userId,
    action: 'ai_system.created',
    entityType: 'ai_system',
    entityId: $newId,
    previousValue: null,       // JSON-encodable or null
    newValue: $data,           // JSON-encodable
    impersonatedBy: $request->impersonatedBy ?? null
);
```

## Error Handling

All exceptions are caught by a global `ErrorHandler` registered in `index.php`. Controllers should throw:

- `ValidationException` → 422
- `AuthenticationException` → 401
- `AuthorizationException` → 403
- `NotFoundException` → 404
- `ConflictException` → 409
- `RateLimitException` → 429

Never catch exceptions in Controllers just to re-format them. Let the ErrorHandler produce the standard envelope.

## Response Envelope

Every response MUST use `Response::success($data, $meta)` or `Response::error($errors, $statusCode, $meta)`. These produce the standard JSON envelope defined in spec Section 9.2. Do not manually construct response arrays.

## Migrations

- One file per logical change: `001_create_tenants.sql`, `002_create_users.sql`, etc.
- Every migration file must be idempotent where possible (`CREATE TABLE IF NOT EXISTS`).
- Destructive migrations (DROP COLUMN, DROP TABLE) go in a separate file with a `-- DESTRUCTIVE` comment header and are only applied after the corresponding code change is confirmed stable.
- Migration runner tracks applied migrations in a `schema_migrations` table.
