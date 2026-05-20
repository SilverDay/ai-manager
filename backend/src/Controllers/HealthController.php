<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

final class HealthController
{
    public function check(Request $request): Response
    {
        $data = [
            'status' => 'ok',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'environment' => 'development',
            'php_version' => PHP_VERSION,
            'services' => [
                'api' => 'healthy',
                // Database connection will be added in S0-WP2-05
                // 'database' => 'healthy',
            ],
        ];

        $meta = [
            'request_id' => uniqid('req_', true),
            'server_time' => time(),
        ];

        return Response::success($data, $meta);
    }

    public function test(Request $request): Response
    {
        $id = $request->getRouteParameter('id');

        $data = [
            'message' => 'Router parameter test',
            'route_parameter_id' => $id,
            'all_route_parameters' => $request->getRouteParameters(),
            'query_parameters' => $request->getQuery(),
        ];

        return Response::success($data);
    }

    public function requestTest(Request $request): Response
    {
        // Test JSON parsing and context features
        $data = [
            'json_parsing' => [
                'has_valid_json' => $request->hasValidJsonBody(),
                'json_error' => $request->getJsonError(),
                'body_params' => $request->getParsedBody(),
            ],
            'context' => [
                'is_authenticated' => $request->isAuthenticated(),
                'user' => $request->getUser(),
                'has_tenant_context' => $request->hasTenantContext(),
                'tenant_id' => $request->getTenantId(),
                'all_context' => $request->getAllContext(),
            ],
            'safe_params' => [
                'test_int' => $request->getBodyParamInt('test_int'),
                'test_string' => $request->getBodyParamString('test_string'),
                'test_bool' => $request->getBodyParamBool('test_bool'),
                'query_int' => $request->getQueryParamInt('num'),
                'query_string' => $request->getQueryParamString('text'),
            ],
            'headers_debug' => [
                'all_headers' => $request->getHeaders(),
                'authorization_header' => $request->getHeader('Authorization'),
                'authorization_header_alt' => $request->getHeader('authorization'),
            ],
        ];

        return Response::success($data);
    }

    public function responseTest(Request $request): Response
    {
        $type = $request->getQueryParam('type', 'success');

        return match ($type) {
            'success' => Response::success(['message' => 'Success response test']),
            'created' => Response::created(['id' => 123, 'message' => 'Resource created']),
            'error' => Response::error('Test error message'),
            'validation' => Response::validationError([
                'name' => ['Name is required'],
                'email' => ['Email must be valid', 'Email is already taken'],
            ]),
            'notfound' => Response::notFound('Test resource not found'),
            'unauthorized' => Response::unauthorized(),
            'forbidden' => Response::forbidden(),
            'ratelimit' => Response::rateLimited(),
            'servererror' => Response::serverError(),
            'paginated' => Response::paginated(
                [['id' => 1], ['id' => 2], ['id' => 3]],
                ['page' => 1, 'per_page' => 3, 'total' => 10, 'total_pages' => 4]
            ),
            'nocontent' => Response::noContent(),
            default => Response::error('Unknown test type'),
        };
    }

    public function errorTest(Request $request): Response
    {
        $type = $request->getQueryParam('type', 'runtime');

        match ($type) {
            'validation' => throw \App\Exceptions\ValidationException::fromFieldErrors([
                'email' => ['Email is required', 'Email must be valid'],
                'name' => ['Name must be at least 2 characters'],
            ]),
            'authentication' => throw \App\Exceptions\AuthenticationException::invalidCredentials(),
            'authorization' => throw \App\Exceptions\AuthorizationException::insufficientRole('Admin'),
            'notfound' => throw \App\Exceptions\NotFoundException::forResource('User', 999),
            'conflict' => throw \App\Exceptions\ConflictException::duplicateResource('User', 'email', 'test@example.com'),
            'ratelimit' => throw \App\Exceptions\RateLimitException::tooManyRequests(120),
            'runtime' => throw new \RuntimeException('Test runtime exception'),
            'invalidarg' => throw new \InvalidArgumentException('Invalid argument provided'),
            'json' => throw new \JsonException('Invalid JSON format'),
            'pdo' => throw new \PDOException('Database connection failed'),
            default => throw new \Exception('Unknown error type: ' . $type),
        };
    }

    public function diagnostics(Request $request): Response
    {
        $config = \App\Core\Config::getInstance();
        $database = new \App\Core\Database($config);

        // Basic configuration diagnostics
        $configDiagnostics = [
            'environment' => $config->get('app.env'),
            'debug_mode' => $config->get('app.debug'),
            'app_name' => $config->get('app.name'),
            'app_url' => $config->get('app.url'),
            'frontend_url' => $config->get('app.frontend_url'),
            'jwt_secret_set' => !empty($config->get('jwt.secret')),
            'jwt_secret_length' => strlen($config->get('jwt.secret', '')),
            'storage_path' => $config->get('storage.path'),
            'storage_writable' => is_writable($config->get('storage.path')),
        ];

        // Database diagnostics
        $dbDiagnostics = $database->getDiagnostics();

        // Environment variables check
        $envVars = [
            'APP_ENV' => $_ENV['APP_ENV'] ?? 'not_set',
            'DB_HOST' => $_ENV['DB_HOST'] ?? 'not_set',
            'DB_DATABASE' => $_ENV['DB_DATABASE'] ?? 'not_set',
            'JWT_SECRET' => !empty($_ENV['JWT_SECRET'] ?? '') ? 'set' : 'not_set',
        ];

        $data = [
            'timestamp' => date('c'),
            'config' => $configDiagnostics,
            'database' => $dbDiagnostics,
            'environment_variables' => $envVars,
            'php_version' => PHP_VERSION,
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring'),
            ],
        ];

        return Response::success($data);
    }
}