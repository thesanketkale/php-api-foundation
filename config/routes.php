<?php

declare(strict_types=1);

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
use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    // Health Probes
    $r->addRoute('GET', '/v1/health', LivenessAction::class);
    $r->addRoute('GET', '/v1/health/ready', ReadinessAction::class);
    $r->addRoute('GET', '/health', LivenessAction::class);
    $r->addRoute('GET', '/health/ready', ReadinessAction::class);

    // Authentication Routes
    $r->addRoute('POST', '/v1/auth/register', RegisterAction::class);
    $r->addRoute('POST', '/v1/auth/login', LoginAction::class);
    $r->addRoute('POST', '/v1/auth/refresh', RefreshTokenAction::class);
    $r->addRoute('POST', '/v1/auth/logout', LogoutAction::class);

    // Current User Profile
    $r->addRoute('GET', '/v1/me', MeAction::class);

    // Notes Resource (Owner-scoped CRUD)
    $r->addRoute('GET', '/v1/notes', ListNotesAction::class);
    $r->addRoute('POST', '/v1/notes', CreateNoteAction::class);
    $r->addRoute('GET', '/v1/notes/{id:[a-f0-9\-]{36}}', GetNoteAction::class);
    $r->addRoute('PUT', '/v1/notes/{id:[a-f0-9\-]{36}}', UpdateNoteAction::class);
    $r->addRoute('DELETE', '/v1/notes/{id:[a-f0-9\-]{36}}', DeleteNoteAction::class);
};
