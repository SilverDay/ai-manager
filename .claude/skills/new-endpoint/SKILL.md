# New API Endpoint

Scaffolds a complete CRUD endpoint for a new resource.

## Usage

Invoke when asked to create a new API resource (e.g., "add vendors endpoint", "create FRIA API").

## Steps

1. **Identify the resource** from the spec. Read the relevant section in `docs/specification.md` for fields, relationships, and business rules.

2. **Create the migration** in `backend/migrations/` if the table doesn't exist yet. Follow the naming convention in database-rules.md. Include all columns from the spec, tenant_id, timestamps, foreign keys, and indexes.

3. **Create the Model** in `backend/src/Models/{Resource}Model.php`:
   - All tenant-scoped methods take `int $tenantId` as first param.
   - Methods: `findById`, `findAll` (with pagination), `create`, `update`, `delete` (soft delete where applicable).
   - Use prepared statements only.

4. **Create the Validator** in `backend/src/Validators/{Resource}Validator.php`:
   - `validateCreate(array $data): array` — returns validated data or throws `ValidationException`.
   - `validateUpdate(array $data): array` — same, but fields are optional.
   - Whitelist approach: only expected fields pass through.

5. **Create the Service** in `backend/src/Services/{Resource}Service.php`:
   - Business logic layer between Controller and Model.
   - Handles audit logging via `AuditService::log()`.
   - Handles versioning logic for versioned resources.
   - Validates business rules (not just field validation).

6. **Create the Controller** in `backend/src/Controllers/{Resource}Controller.php`:
   - One public method per action: `index`, `show`, `store`, `update`, `destroy`.
   - Each method: validate → call service → return Response envelope.
   - Never access the database directly.

7. **Register routes** in `backend/src/Routes/api.php`:
   ```php
   $router->get('/v1/resources', [ResourceController::class, 'index'], ['roles' => ['Admin', 'AI Owner']]);
   $router->post('/v1/resources', [ResourceController::class, 'store'], ['roles' => ['Admin', 'AI Owner']]);
   $router->get('/v1/resources/{id}', [ResourceController::class, 'show'], ['roles' => ['Admin', 'AI Owner', 'Auditor']]);
   $router->put('/v1/resources/{id}', [ResourceController::class, 'update'], ['roles' => ['Admin', 'AI Owner']]);
   ```

8. **Create tests** in `backend/tests/Integration/Controllers/{Resource}ControllerTest.php`:
   - Minimum: happy path, auth, authz, validation, tenant isolation, not found (6 tests per action).

9. **Create the frontend API module** in `frontend/src/api/{resource}.js`.

10. **Verify** by running `./vendor/bin/phpunit` and checking for green.
