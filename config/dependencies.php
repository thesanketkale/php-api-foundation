<?php

declare(strict_types=1);

use App\Domain\Auth\RefreshTokenRepositoryInterface;
use App\Domain\Auth\TokenFamilyService;
use App\Domain\Note\NoteRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Http\Action\Auth\LoginAction;
use App\Http\Action\Auth\LogoutAction;
use App\Http\Action\Auth\MeAction;
use App\Http\Action\Auth\RefreshTokenAction;
use App\Http\Action\Auth\RegisterAction;
use App\Http\Action\Health\LivenessAction;
use App\Http\Action\Health\ReadinessAction;
use App\Http\Action\Notes\CreateNoteAction;
use App\Http\Action\Notes\DeleteNoteAction;
use App\Http\Action\Notes\GetNoteAction;
use App\Http\Action\Notes\ListNotesAction;
use App\Http\Action\Notes\UpdateNoteAction;
use App\Http\Middleware\AccessLogMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\ErrorHandlerMiddleware;
use App\Http\Middleware\JsonBodyParserMiddleware;
use App\Http\Middleware\JwtAuthenticationMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Infrastructure\Database\PdoConnectionFactory;
use App\Infrastructure\Persistence\PdoNoteRepository;
use App\Infrastructure\Persistence\PdoRefreshTokenRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use App\Infrastructure\RateLimiter\RateLimiterFactory;
use App\Infrastructure\RateLimiter\RateLimiterInterface;
use App\Infrastructure\Token\JwtService;
use App\Support\Config;
use App\Support\Validator;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    // Configuration
    Config::class => static function (): Config {
        /** @var array<string, mixed> $env */
        $env = $_ENV;
        if (!isset($env['APP_ENV'])) {
            $root = dirname(__DIR__);
            if (file_exists($root . '/.env')) {
                $dotenv = Dotenv\Dotenv::createImmutable($root);
                $dotenv->safeLoad();
                /** @var array<string, mixed> $env */
                $env = $_ENV;
            }
        }
        return new Config($env);
    },

    // Database Connection
    PDO::class => static function (ContainerInterface $c): PDO {
        /** @var Config $config */
        $config = $c->get(Config::class);
        return PdoConnectionFactory::create($config);
    },

    // Logger
    LoggerInterface::class => static function (ContainerInterface $c): LoggerInterface {
        /** @var Config $config */
        $config = $c->get(Config::class);
        $logger = new Logger($config->getString('LOG_CHANNEL', 'api'));
        $logPath = dirname(__DIR__) . '/' . $config->getString('LOG_PATH', 'storage/logs/app.log');

        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logger->pushHandler(new RotatingFileHandler($logPath, 7, Logger::DEBUG));
        if ($config->getString('APP_ENV') === 'development' || $config->getBool('APP_DEBUG', false)) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        }

        return $logger;
    },

    // Repositories
    UserRepositoryInterface::class => static function (ContainerInterface $c): UserRepositoryInterface {
        /** @var PDO $pdo */
        $pdo = $c->get(PDO::class);
        return new PdoUserRepository($pdo);
    },

    RefreshTokenRepositoryInterface::class => static function (ContainerInterface $c): RefreshTokenRepositoryInterface {
        /** @var PDO $pdo */
        $pdo = $c->get(PDO::class);
        return new PdoRefreshTokenRepository($pdo);
    },

    NoteRepositoryInterface::class => static function (ContainerInterface $c): NoteRepositoryInterface {
        /** @var PDO $pdo */
        $pdo = $c->get(PDO::class);
        return new PdoNoteRepository($pdo);
    },

    // Rate Limiter
    RateLimiterInterface::class => static function (ContainerInterface $c): RateLimiterInterface {
        /** @var Config $config */
        $config = $c->get(Config::class);
        return RateLimiterFactory::create($config);
    },

    // Services
    JwtService::class => static function (ContainerInterface $c): JwtService {
        /** @var Config $config */
        $config = $c->get(Config::class);
        return new JwtService($config);
    },

    TokenFamilyService::class => static function (ContainerInterface $c): TokenFamilyService {
        /** @var RefreshTokenRepositoryInterface $refreshRepo */
        $refreshRepo = $c->get(RefreshTokenRepositoryInterface::class);
        /** @var UserRepositoryInterface $userRepo */
        $userRepo = $c->get(UserRepositoryInterface::class);
        /** @var JwtService $jwtService */
        $jwtService = $c->get(JwtService::class);
        /** @var PDO $pdo */
        $pdo = $c->get(PDO::class);
        /** @var Config $config */
        $config = $c->get(Config::class);

        return new TokenFamilyService($refreshRepo, $userRepo, $jwtService, $pdo, $config);
    },

    Validator::class => static function (): Validator {
        return new Validator();
    },

    // Middlewares
    RequestIdMiddleware::class => static fn () => new RequestIdMiddleware(),
    SecurityHeadersMiddleware::class => static fn () => new SecurityHeadersMiddleware(),
    CorsMiddleware::class => static function (ContainerInterface $c): CorsMiddleware {
        /** @var Config $config */
        $config = $c->get(Config::class);
        return new CorsMiddleware($config);
    },
    RateLimitMiddleware::class => static function (ContainerInterface $c): RateLimitMiddleware {
        /** @var RateLimiterInterface $rateLimiter */
        $rateLimiter = $c->get(RateLimiterInterface::class);
        /** @var Config $config */
        $config = $c->get(Config::class);
        return new RateLimitMiddleware($rateLimiter, $config);
    },
    JsonBodyParserMiddleware::class => static fn () => new JsonBodyParserMiddleware(),
    JwtAuthenticationMiddleware::class => static function (ContainerInterface $c): JwtAuthenticationMiddleware {
        /** @var JwtService $jwtService */
        $jwtService = $c->get(JwtService::class);
        return new JwtAuthenticationMiddleware($jwtService);
    },
    ErrorHandlerMiddleware::class => static function (ContainerInterface $c): ErrorHandlerMiddleware {
        /** @var LoggerInterface $logger */
        $logger = $c->get(LoggerInterface::class);
        /** @var Config $config */
        $config = $c->get(Config::class);
        return new ErrorHandlerMiddleware($logger, $config);
    },
    AccessLogMiddleware::class => static function (ContainerInterface $c): AccessLogMiddleware {
        /** @var LoggerInterface $logger */
        $logger = $c->get(LoggerInterface::class);
        return new AccessLogMiddleware($logger);
    },

    // Actions
    LivenessAction::class => static fn () => new LivenessAction(),
    ReadinessAction::class => static function (ContainerInterface $c): ReadinessAction {
        /** @var PDO $pdo */
        $pdo = $c->get(PDO::class);
        return new ReadinessAction($pdo);
    },

    RegisterAction::class => static function (ContainerInterface $c): RegisterAction {
        /** @var UserRepositoryInterface $userRepo */
        $userRepo = $c->get(UserRepositoryInterface::class);
        return new RegisterAction($userRepo);
    },
    LoginAction::class => static function (ContainerInterface $c): LoginAction {
        /** @var UserRepositoryInterface $userRepo */
        $userRepo = $c->get(UserRepositoryInterface::class);
        /** @var TokenFamilyService $tokenService */
        $tokenService = $c->get(TokenFamilyService::class);
        return new LoginAction($userRepo, $tokenService);
    },
    RefreshTokenAction::class => static function (ContainerInterface $c): RefreshTokenAction {
        /** @var TokenFamilyService $tokenService */
        $tokenService = $c->get(TokenFamilyService::class);
        return new RefreshTokenAction($tokenService);
    },
    LogoutAction::class => static function (ContainerInterface $c): LogoutAction {
        /** @var TokenFamilyService $tokenService */
        $tokenService = $c->get(TokenFamilyService::class);
        return new LogoutAction($tokenService);
    },
    MeAction::class => static function (ContainerInterface $c): MeAction {
        /** @var UserRepositoryInterface $userRepo */
        $userRepo = $c->get(UserRepositoryInterface::class);
        return new MeAction($userRepo);
    },

    ListNotesAction::class => static function (ContainerInterface $c): ListNotesAction {
        /** @var NoteRepositoryInterface $noteRepo */
        $noteRepo = $c->get(NoteRepositoryInterface::class);
        return new ListNotesAction($noteRepo);
    },
    GetNoteAction::class => static function (ContainerInterface $c): GetNoteAction {
        /** @var NoteRepositoryInterface $noteRepo */
        $noteRepo = $c->get(NoteRepositoryInterface::class);
        return new GetNoteAction($noteRepo);
    },
    CreateNoteAction::class => static function (ContainerInterface $c): CreateNoteAction {
        /** @var NoteRepositoryInterface $noteRepo */
        $noteRepo = $c->get(NoteRepositoryInterface::class);
        return new CreateNoteAction($noteRepo);
    },
    UpdateNoteAction::class => static function (ContainerInterface $c): UpdateNoteAction {
        /** @var NoteRepositoryInterface $noteRepo */
        $noteRepo = $c->get(NoteRepositoryInterface::class);
        return new UpdateNoteAction($noteRepo);
    },
    DeleteNoteAction::class => static function (ContainerInterface $c): DeleteNoteAction {
        /** @var NoteRepositoryInterface $noteRepo */
        $noteRepo = $c->get(NoteRepositoryInterface::class);
        return new DeleteNoteAction($noteRepo);
    },
];
