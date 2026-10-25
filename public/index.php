<?php

declare(strict_types=1);

use App\Http\App;
use Dotenv\Dotenv;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables if .env exists
$rootDir = dirname(__DIR__);
if (file_exists($rootDir . '/.env')) {
    $dotenv = Dotenv::createImmutable($rootDir);
    $dotenv->safeLoad();
}

$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory
);

$request = $creator->fromGlobals();

$app = new App();
$response = $app->handle($request);

// Send HTTP status code
http_response_code($response->getStatusCode());

// Send HTTP response headers
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}

// Send HTTP response body
echo (string) $response->getBody();
