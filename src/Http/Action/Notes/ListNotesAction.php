<?php

declare(strict_types=1);

namespace App\Http\Action\Notes;

use App\Domain\Note\NoteRepositoryInterface;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ListNotesAction implements RequestHandlerInterface
{
    private NoteRepositoryInterface $noteRepository;

    public function __construct(NoteRepositoryInterface $noteRepository)
    {
        $this->noteRepository = $noteRepository;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
        $search = isset($params['search']) ? trim((string) $params['search']) : null;
        $sort = (string) ($params['sort'] ?? 'created_at');
        $order = (string) ($params['order'] ?? 'desc');

        $offset = ($page - 1) * $perPage;

        $notes = $this->noteRepository->findByFilter($userId, $search, $sort, $order, $perPage, $offset);
        $totalItems = $this->noteRepository->countByFilter($userId, $search);
        $totalPages = (int) ceil($totalItems / $perPage);

        $data = array_map(static fn ($note) => $note->toArray(), $notes);

        $payload = [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => max(1, $totalPages),
            ],
        ];

        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES)
        );
    }
}
