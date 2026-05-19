<?php

declare(strict_types=1);

// Bootstrap the application
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Application;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors to clients
ini_set('log_errors', '1');

// Set default headers
header('Content-Type: application/json');

// Handle CORS for development (will be moved to middleware in Sprint 1)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

try {
    // Determine debug mode from environment
    $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

    // Register global error handler
    ErrorHandler::register($debug);

    // Create application instance
    $app = new Application($debug);

    // Create request from globals
    $request = Request::fromGlobals();

    // Process the request
    $response = $app->handle($request);

    // Send the response
    $response->send();

} catch (Throwable $e) {
    // Fallback error handler (should not be reached due to ErrorHandler::register)
    error_log("FALLBACK: Unhandled exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    $errorResponse = Response::error(
        ['message' => 'Internal server error'],
        500
    );

    $errorResponse->send();
}