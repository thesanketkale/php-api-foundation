<?php

declare(strict_types=1);

/**
 * deploy/ftp/migrate.php: HMAC-authenticated one-shot database migration trigger
 * for shared hosting environments where direct SSH/CLI access is unavailable.
 *
 * Security:
 * - Requires HTTP POST with valid HMAC-SHA256 signature in X-Deploy-Signature
 * - Validates timestamp within 300-second window to prevent replay attacks
 * - Reads DEPLOY_SECRET from server environment or root .env
 * - Self-destructs (unlinks itself) after single execution
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2);

// 1. Verify Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// 2. Resolve DEPLOY_SECRET
$secret = getenv('DEPLOY_SECRET') ?: '';
if ($secret === '' && file_exists($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), 'DEPLOY_SECRET=')) {
            $secret = trim(substr(trim($line), 14), " \t\n\r\0\x0B\"'");
            break;
        }
    }
}

if ($secret === '') {
    http_response_code(500);
    echo json_encode(['error' => 'DEPLOY_SECRET is not configured on the server']);
    exit;
}

// 3. Verify HMAC Signature
$timestamp = $_SERVER['HTTP_X_DEPLOY_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_DEPLOY_SIGNATURE'] ?? '';

if ($timestamp === '' || $signature === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Missing deployment authentication headers']);
    exit;
}

$now = time();
$reqTime = (int) $timestamp;
if (abs($now - $reqTime) > 300) {
    http_response_code(403);
    echo json_encode(['error' => 'Deployment request timestamp expired']);
    exit;
}

$expectedSignature = hash_hmac('sha256', (string) $reqTime, $secret);
if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid deployment signature']);
    exit;
}

// 4. Execute Migrations via Phinx
require $root . '/vendor/autoload.php';

try {
    $phinxApp = new Phinx\Console\PhinxApplication();
    $command = $phinxApp->find('migrate');

    $input = new Symfony\Component\Console\Input\ArrayInput([
        'command' => 'migrate',
        '--configuration' => $root . '/phinx.php',
        '--environment' => 'default',
    ]);

    $outputStream = fopen('php://memory', 'r+');
    $output = new Symfony\Component\Console\Output\StreamOutput($outputStream);

    $exitCode = $command->run($input, $output);
    rewind($outputStream);
    $outputString = stream_get_contents($outputStream);
    fclose($outputStream);

    // 5. Self-destruct file
    @unlink(__FILE__);

    if ($exitCode === 0) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'exit_code' => $exitCode,
            'output' => $outputString,
            'timestamp' => time(),
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'exit_code' => $exitCode,
            'error' => $outputString,
            'timestamp' => time(),
        ]);
    }
} catch (Throwable $e) {
    @unlink(__FILE__);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
