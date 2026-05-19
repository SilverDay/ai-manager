<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => [
        'status' => 'ok',
        'timestamp' => date('c'),
        'version' => '1.0.0',
        'php_version' => PHP_VERSION
    ],
    'errors' => [],
    'meta' => []
]);
?>